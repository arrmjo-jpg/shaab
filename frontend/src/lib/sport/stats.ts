import 'server-only';
import { z } from 'zod';

// مصدر البيانات الوحيد = 365Scores العامّ `web/stats/?competitions={id}` (هدّافو البطولة). لا Mock.
// (مُختبَر: stats.athletesStats[] مجموعات؛ مجموعة «الأهداف» = صفوف الهدّافين؛ stats.typeId=1=أهداف؛ اسم الفريق من competitors[].)
const BASE = 'https://webws.365scores.com/web';
const COMMON = 'appTypeId=5&langId=27&timezoneName=Asia/Amman&userCountryId=6';

// البطولات المعروضة في ويدجت «الأهداف» — **قرار تحريريّ** (إقليميّ: اختاره صاحب المنتج). IDs ثابتة من 365.
// بطولات اليوم «الشائعة» مغمورة وبلا جداول هدّافين ⇒ القائمة منسّقة لا مشتقّة من مباريات اليوم.
export const TOP_SCORER_COMPETITIONS = [
  5930, // كأس العالم (التبويب الأوّل/الافتراضيّ — طلب المستخدم)
  5635, // كأس الأردن
  649, // الدوري السعودي
  552, // الدوري المصري
];

const Entity = z
  .object({
    id: z.number(),
    name: z.string(),
    competitorId: z.number().nullish(),
    positionName: z.string().nullish(),
    imageVersion: z.number().nullish(),
  })
  .passthrough();

const Row = z
  .object({
    entity: Entity,
    stats: z.array(z.object({ typeId: z.number(), value: z.string() }).passthrough()).nullish(),
  })
  .passthrough();

const StatGroup = z
  .object({
    id: z.number(),
    name: z.string(),
    competitionId: z.number().nullish(),
    statsTypes: z.array(z.object({ name: z.string().nullish() }).passthrough()).nullish(),
    rows: z.array(Row).nullish(),
  })
  .passthrough();

const StatsResponse = z
  .object({
    stats: z.object({ athletesStats: z.array(StatGroup).nullish() }).passthrough().nullish(),
    competitions: z
      .array(
        z
          .object({ id: z.number(), name: z.string(), countryId: z.number().nullish(), imageVersion: z.number().nullish() })
          .passthrough(),
      )
      .nullish(),
    competitors: z.array(z.object({ id: z.number(), name: z.string(), imageVersion: z.number().nullish() }).passthrough()).nullish(),
  })
  .passthrough();

export interface Scorer {
  id: number;
  name: string;
  position: string | null;
  team: string | null;
  goals: string;
  image: string | null;
}
export interface ScorerCompetition {
  competitionId: number;
  competitionName: string;
  logo: string | null;
  scorers: Scorer[];
}

// صورة اللاعب — نفس تحويل 365 (قصّ دائريّ على الوجه).
function athleteImg(id: number, version: number | null | undefined, size = 64): string | null {
  if (version == null) return null;
  return `https://imagecache.365scores.com/image/upload/f_png,w_${size},h_${size},c_limit,q_auto:eco,dpr_2,d_Athletes:default.png,r_max,c_thumb,g_face,z_0.65/v${version}/Athletes/${id}`;
}

// شعار البطولة (الافتراضيّ = علم الدولة المستديرة، نمط 365).
function competitionLogo(id: number, countryId: number | null | undefined, version: number | null | undefined): string | null {
  if (version == null) return null;
  const def = countryId != null ? `Countries:Round:${countryId}` : 'Competitions:default1';
  return `https://imagecache.365scores.com/image/upload/f_png,w_40,h_40,c_limit,q_auto:eco,dpr_2,d_${def}.png/v${version}/Competitions/${id}`;
}

// شعار الفريق.
function teamLogo(id: number, version: number | null | undefined, size = 40): string | null {
  if (version == null) return null;
  return `https://imagecache.365scores.com/image/upload/f_png,w_${size},h_${size},c_limit,q_auto:eco,dpr_2,d_Competitors:default1.png/v${version}/Competitors/${id}`;
}

async function fetchScorers(competitionId: number, limit: number): Promise<ScorerCompetition | null> {
  try {
    const res = await fetch(`${BASE}/stats/?${COMMON}&competitions=${competitionId}`, {
      signal: AbortSignal.timeout(6000),
      next: { revalidate: 3600, tags: ['sport-stats'] },
    });
    if (!res.ok) return null;
    const parsed = StatsResponse.safeParse(await res.json());
    if (!parsed.success) return null;
    const data = parsed.data;
    const groups = data.stats?.athletesStats ?? [];
    // مجموعة «الأهداف» فقط (لا «أهداف + صناعة»)؛ langId=27 ⇒ الاسم عربيّ ثابت، واحتياط id===1.
    const goals = groups.find((g) => g.name === 'الأهداف') ?? groups.find((g) => g.id === 1);
    const rows = goals?.rows ?? [];
    if (!rows.length) return null;
    const teamName = new Map<number, string>();
    for (const c of data.competitors ?? []) teamName.set(c.id, c.name);
    const comp = (data.competitions ?? []).find((c) => c.id === competitionId);
    const scorers: Scorer[] = rows.slice(0, limit).map((r) => {
      const g = (r.stats ?? []).find((s) => s.typeId === 1) ?? (r.stats ?? [])[0];
      return {
        id: r.entity.id,
        name: r.entity.name,
        position: r.entity.positionName ?? null,
        team: r.entity.competitorId != null ? (teamName.get(r.entity.competitorId) ?? null) : null,
        goals: g?.value ?? '0',
        image: athleteImg(r.entity.id, r.entity.imageVersion),
      };
    });
    if (!comp) return null;
    return {
      competitionId,
      competitionName: comp.name,
      logo: competitionLogo(comp.id, comp.countryId, comp.imageVersion),
      scorers,
    };
  } catch {
    return null;
  }
}

// هدّافو عدّة بطولات (طلب لكلّ بطولة على حدة — الاستدعاء متعدّد البطولات لا يُرجع إلا الأولى).
// تُستبعَد البطولات بلا هدّافين (خارج الموسم) ⇒ لا تبويبات فارغة، لا تلفيق.
export async function getTopScorers(competitionIds: number[], limit = 5): Promise<ScorerCompetition[]> {
  if (!competitionIds.length) return [];
  const results = await Promise.all(competitionIds.map((id) => fetchScorers(id, limit)));
  return results.filter((r): r is ScorerCompetition => r !== null && r.scorers.length > 0);
}

// ===== صفحة البطولة — تبويب الإحصائيات (كلّ الفئات) =====
// (هدّافون/صنّاع/أهداف+صناعة/بطاقات/شباك نظيفة) — القيمة الرئيسيّة = stats[0]، الوحدة = statsTypes[0].name.
export interface StatLeader {
  id: number;
  name: string;
  position: string | null;
  team: string | null;
  image: string | null;
  value: string;
}
export interface StatCategory {
  id: number;
  title: string;
  unit: string | null;
  leaders: StatLeader[];
}
export interface CompetitionStats {
  competition: { id: number; name: string; logo: string | null };
  categories: StatCategory[];
}

export async function getCompetitionStats(competitionId: number, limit = 20): Promise<CompetitionStats | null> {
  if (!Number.isInteger(competitionId) || competitionId <= 0) return null;
  try {
    const res = await fetch(`${BASE}/stats/?${COMMON}&competitions=${competitionId}`, {
      signal: AbortSignal.timeout(6000),
      next: { revalidate: 3600, tags: ['sport-stats'] },
    });
    if (!res.ok) return null;
    const parsed = StatsResponse.safeParse(await res.json());
    if (!parsed.success) return null;
    const data = parsed.data;
    const comp = (data.competitions ?? []).find((c) => c.id === competitionId);
    if (!comp) return null;
    const teamName = new Map<number, string>();
    for (const c of data.competitors ?? []) teamName.set(c.id, c.name);
    const categories: StatCategory[] = (data.stats?.athletesStats ?? [])
      .map((g) => ({
        id: g.id,
        title: g.name,
        unit: g.statsTypes?.[0]?.name ?? null,
        leaders: (g.rows ?? []).slice(0, limit).map((r) => ({
          id: r.entity.id,
          name: r.entity.name,
          position: r.entity.positionName ?? null,
          team: r.entity.competitorId != null ? (teamName.get(r.entity.competitorId) ?? null) : null,
          image: athleteImg(r.entity.id, r.entity.imageVersion),
          value: (r.stats ?? [])[0]?.value ?? '0',
        })),
      }))
      .filter((c) => c.leaders.length > 0);
    if (!categories.length) return null;
    return {
      competition: { id: comp.id, name: comp.name, logo: competitionLogo(comp.id, comp.countryId, comp.imageVersion) },
      categories,
    };
  } catch {
    return null;
  }
}

// ===== صفحة البطولة — meta + الفرق + الأبطال =====
export interface CompetitionMeta {
  id: number;
  name: string;
  logo: string | null;
  country: string | null;
  hasStats: boolean;
  hasHistory: boolean;
  hasBrackets: boolean;
  hasStandings: boolean;
}
export interface TeamLite {
  id: number;
  name: string;
  logo: string | null;
}
export interface ChampionRow {
  seasonNum: number;
  title: string | null; // اسم الإصدار «قطر 2022»
  winner: { id: number | null; name: string | null; logo: string | null };
  result: string | null; // سطر النتيجة الجاهز من 365 «فرنسا 4-2 (بعد ضربات الترجيح)»
  finalGameId: number | null; // معرّف مباراة النهائيّ (للرابط)
}

const CompMeta = z
  .object({
    id: z.number(),
    name: z.string(),
    countryId: z.number().nullish(),
    imageVersion: z.number().nullish(),
    hasStats: z.boolean().nullish(),
    hasHistory: z.boolean().nullish(),
    hasBrackets: z.boolean().nullish(),
    hasStandings: z.boolean().nullish(),
  })
  .passthrough();
const CompMetaResponse = z
  .object({
    competitions: z.array(CompMeta).nullish(),
    countries: z.array(z.object({ id: z.number(), name: z.string() }).passthrough()).nullish(),
  })
  .passthrough();

// meta البطولة (خفيف) — الاسم/الشعار/الدولة + أعلام الأقسام المتاحة (hasStats/hasHistory/hasBrackets) لاشتقاق التبويبات.
export async function getCompetitionMeta(id: number): Promise<CompetitionMeta | null> {
  if (!Number.isInteger(id) || id <= 0) return null;
  try {
    const res = await fetch(`${BASE}/competitions/?${COMMON}&competitions=${id}`, {
      signal: AbortSignal.timeout(6000),
      next: { revalidate: 3600, tags: ['sport-stats'] },
    });
    if (!res.ok) return null;
    const parsed = CompMetaResponse.safeParse(await res.json());
    if (!parsed.success) return null;
    const comps = parsed.data.competitions ?? [];
    const comp = comps.find((c) => c.id === id) ?? comps[0];
    if (!comp) return null;
    const country = (parsed.data.countries ?? []).find((c) => c.id === comp.countryId)?.name ?? null;
    return {
      id: comp.id,
      name: comp.name,
      logo: competitionLogo(comp.id, comp.countryId, comp.imageVersion),
      country,
      hasStats: !!comp.hasStats,
      hasHistory: !!comp.hasHistory,
      hasBrackets: !!comp.hasBrackets,
      hasStandings: !!comp.hasStandings,
    };
  } catch {
    return null;
  }
}

// فرق البطولة — من `competitors[]` لنداء web/stats (مرتّبة أبجديّاً).
export async function getCompetitionTeams(id: number): Promise<TeamLite[]> {
  if (!Number.isInteger(id) || id <= 0) return [];
  try {
    const res = await fetch(`${BASE}/stats/?${COMMON}&competitions=${id}`, {
      signal: AbortSignal.timeout(6000),
      next: { revalidate: 3600, tags: ['sport-stats'] },
    });
    if (!res.ok) return [];
    const parsed = StatsResponse.safeParse(await res.json());
    if (!parsed.success) return [];
    return (parsed.data.competitors ?? [])
      .map((c) => ({ id: c.id, name: c.name, logo: teamLogo(c.id, c.imageVersion) }))
      .sort((a, b) => a.name.localeCompare(b.name, 'ar'));
  } catch {
    return [];
  }
}

// ===== صفحة البطولة — خروج المغلوب (شجرة الأدوار الإقصائيّة) =====
// `web/brackets`: مراحل الإقصاء (دور الـ32→النهائي)، كلّ مواجهة (group) = مشاركان (`name`/صيغة تأهّل مثل
// «1 ه» أو «الفائز في المباراة 74») + موعد (`games[0].startTime`) + (gameId/شعار حين تتحدّد الفرق وتُسنَد
// competitorId/imageVersion). نعرضها كما يردّها المصدر — حقيقيّة، بلا تلفيق فرق. مرحلة المجموعات تُستبعَد (تبويب «المجموعات»).
export interface BracketParticipant {
  name: string;
  isQualified: boolean;
  logo: string | null;
}
export interface BracketMatch {
  title: string | null;
  date: string | null;
  gameId: number | null;
  home: BracketParticipant | null;
  away: BracketParticipant | null;
}
export interface BracketStageView {
  name: string;
  matches: BracketMatch[];
}

const BracketParticipantSchema = z
  .object({
    name: z.string().nullish(),
    symbolicName: z.string().nullish(),
    isQualified: z.boolean().nullish(),
    competitorId: z.number().nullish(),
    imageVersion: z.number().nullish(),
  })
  .passthrough();
const BracketGroupSchema = z
  .object({
    name: z.string().nullish(),
    participants: z.array(BracketParticipantSchema).nullish(),
    games: z.array(z.object({ id: z.number().nullish(), startTime: z.string().nullish() }).passthrough()).nullish(),
  })
  .passthrough();
const BracketsResponse = z
  .object({
    brackets: z
      .array(
        z
          .object({
            stages: z
              .array(z.object({ name: z.string().nullish(), groups: z.array(BracketGroupSchema).nullish() }).passthrough())
              .nullish(),
          })
          .passthrough(),
      )
      .nullish(),
  })
  .passthrough();

export async function getCompetitionBrackets(competitionId: number): Promise<BracketStageView[]> {
  if (!Number.isInteger(competitionId) || competitionId <= 0) return [];
  try {
    const res = await fetch(`${BASE}/brackets/?${COMMON}&competitions=${competitionId}`, {
      signal: AbortSignal.timeout(6000),
      next: { revalidate: 3600, tags: ['sport-stats'] },
    });
    if (!res.ok) return [];
    const parsed = BracketsResponse.safeParse(await res.json());
    if (!parsed.success) return [];
    const toParticipant = (p: z.infer<typeof BracketParticipantSchema>): BracketParticipant => ({
      name: p.name || p.symbolicName || '—',
      isQualified: !!p.isQualified,
      logo: p.competitorId != null ? teamLogo(p.competitorId, p.imageVersion ?? null, 28) : null,
    });
    return (parsed.data.brackets?.[0]?.stages ?? [])
      .map((s) => ({
        name: s.name ?? '',
        matches: (s.groups ?? [])
          .filter((g) => (g.participants?.length ?? 0) >= 2)
          .map((g) => {
            const ps = g.participants ?? [];
            const game = g.games?.[0];
            return {
              title: g.name ?? null,
              date: game?.startTime ?? null,
              gameId: game?.id ?? null,
              home: ps[0] ? toParticipant(ps[0]) : null,
              away: ps[1] ? toParticipant(ps[1]) : null,
            };
          }),
      }))
      .filter((s) => s.name.length > 0 && s.matches.length > 0);
  } catch {
    return [];
  }
}

// ===== صفحة الفريق `/sport/team/[id]` =====
// المصدر web/competitors/?competitors={id} (معلومات الفريق + بطولاته + mainCompetitionId). لا نقطة مباريات للفريق
// تعمل (competitor= يُتجاهَل) ⇒ لا fixtures مُلفَّقة؛ الصفحة = ترويسة + ترتيب دوريه الرئيس + بطولاته (روابط).
export interface TeamPage {
  id: number;
  name: string;
  logo: string | null;
  country: string | null;
  mainCompetitionId: number | null;
  competitions: { id: number; name: string; logo: string | null }[];
}

const TeamResponse = z
  .object({
    competitors: z
      .array(
        z
          .object({
            id: z.number(),
            name: z.string(),
            countryId: z.number().nullish(),
            imageVersion: z.number().nullish(),
            mainCompetitionId: z.number().nullish(),
          })
          .passthrough(),
      )
      .nullish(),
    competitions: z
      .array(z.object({ id: z.number(), name: z.string(), countryId: z.number().nullish(), imageVersion: z.number().nullish() }).passthrough())
      .nullish(),
    countries: z.array(z.object({ id: z.number(), name: z.string() }).passthrough()).nullish(),
  })
  .passthrough();

export async function getTeam(id: number): Promise<TeamPage | null> {
  if (!Number.isInteger(id) || id <= 0) return null;
  try {
    const res = await fetch(`${BASE}/competitors/?${COMMON}&competitors=${id}`, {
      signal: AbortSignal.timeout(6000),
      next: { revalidate: 3600, tags: ['sport-stats'] },
    });
    if (!res.ok) return null;
    const parsed = TeamResponse.safeParse(await res.json());
    if (!parsed.success) return null;
    const data = parsed.data;
    const t = (data.competitors ?? []).find((c) => c.id === id);
    if (!t) return null;
    const country = (data.countries ?? []).find((c) => c.id === t.countryId)?.name ?? null;
    const competitions = (data.competitions ?? []).map((c) => ({
      id: c.id,
      name: c.name,
      logo: competitionLogo(c.id, c.countryId, c.imageVersion),
    }));
    return {
      id: t.id,
      name: t.name,
      logo: teamLogo(t.id, t.imageVersion, 64),
      country,
      mainCompetitionId: t.mainCompetitionId ?? null,
      competitions,
    };
  } catch {
    return null;
  }
}

const HistoryParticipant = z
  .object({ name: z.string().nullish(), competitorId: z.number().nullish(), isQualified: z.boolean().nullish() })
  .passthrough();
const HistoryGame = z
  .object({ gameId: z.number().nullish(), game: z.object({ id: z.number().nullish() }).passthrough().nullish() })
  .passthrough();
const HistoryResponse = z
  .object({
    table: z
      .object({
        rows: z
          .array(
            z
              .object({
                seasonNum: z.number().nullish(),
                entityId: z.number().nullish(), // البطل
                title: z.string().nullish(), // الإصدار «قطر 2022»
                secondaryTitle: z.string().nullish(), // سطر النتيجة الجاهز
                group: z
                  .object({ participants: z.array(HistoryParticipant).nullish(), games: z.array(HistoryGame).nullish() })
                  .passthrough()
                  .nullish(),
              })
              .passthrough(),
          )
          .nullish(),
      })
      .passthrough()
      .nullish(),
    competitors: z.array(z.object({ id: z.number(), name: z.string().nullish(), imageVersion: z.number().nullish() }).passthrough()).nullish(),
  })
  .passthrough();

// ===== صفحة البطولة + الرئيسية — الترتيب (standings) =====
// المصدر `web/standings` (فارغ للكؤوس، مليء للدوريات). الصفّ: مركز/فريق/لعب/فوز/تعادل/خسارة/له/عليه/فارق/نقاط +
// آخر ٥ نتائج (outcome 1=فوز 2=تعادل 0=خسارة، مع gameId للرابط) + لون منطقة (destinations) + تاج البطل.
export interface FormResult {
  outcome: number;
  gameId: number | null;
}
export interface StandingRow {
  rank: number;
  isWinner: boolean;
  zoneColor: string | null;
  groupNum: number | null;
  team: { id: number; name: string; logo: string | null };
  played: number;
  won: number;
  draw: number;
  lost: number;
  goalsFor: number;
  goalsAgainst: number;
  diff: number;
  points: number;
  form: FormResult[];
}
export interface StandingsZone {
  name: string;
  color: string;
}
export interface StandingsGroup {
  num: number;
  name: string;
}
export interface Standings {
  competition: { id: number; name: string; logo: string | null };
  rows: StandingRow[];
  zones: StandingsZone[];
  groups: StandingsGroup[];
}

const StandRow = z
  .object({
    competitor: z.object({ id: z.number(), name: z.string(), imageVersion: z.number().nullish() }).passthrough(),
    gamePlayed: z.number().nullish(),
    gamesWon: z.number().nullish(),
    gamesLost: z.number().nullish(),
    gamesEven: z.number().nullish(),
    for: z.number().nullish(),
    against: z.number().nullish(),
    ratio: z.number().nullish(),
    points: z.number().nullish(),
    position: z.number().nullish(),
    groupNum: z.number().nullish(),
    isWinner: z.boolean().nullish(),
    destinationNum: z.number().nullish(),
    detailedRecentForm: z.array(z.object({ id: z.number().nullish(), outcome: z.number().nullish() }).passthrough()).nullish(),
  })
  .passthrough();
const StandGroup = z
  .object({
    rows: z.array(StandRow).nullish(),
    groups: z.array(z.object({ num: z.number(), name: z.string().nullish() }).passthrough()).nullish(),
    destinations: z.array(z.object({ num: z.number(), name: z.string().nullish(), color: z.string().nullish() }).passthrough()).nullish(),
  })
  .passthrough();
const StandingsResponse = z
  .object({
    standings: z.array(StandGroup).nullish(),
    competitions: z
      .array(z.object({ id: z.number(), name: z.string(), countryId: z.number().nullish(), imageVersion: z.number().nullish() }).passthrough())
      .nullish(),
  })
  .passthrough();

export async function getStandings(competitionId: number): Promise<Standings | null> {
  if (!Number.isInteger(competitionId) || competitionId <= 0) return null;
  try {
    const res = await fetch(`${BASE}/standings/?${COMMON}&competitions=${competitionId}`, {
      signal: AbortSignal.timeout(6000),
      next: { revalidate: 3600, tags: ['sport-stats'] },
    });
    if (!res.ok) return null;
    const parsed = StandingsResponse.safeParse(await res.json());
    if (!parsed.success) return null;
    const data = parsed.data;
    const group = (data.standings ?? [])[0];
    if (!group?.rows?.length) return null;
    const comp = (data.competitions ?? []).find((c) => c.id === competitionId) ?? (data.competitions ?? [])[0];
    const zoneColor = new Map<number, string>();
    for (const d of group.destinations ?? []) if (d.color) zoneColor.set(d.num, d.color);
    const rows: StandingRow[] = group.rows.map((r, i) => ({
      rank: r.position ?? i + 1,
      isWinner: !!r.isWinner,
      zoneColor: r.destinationNum != null ? (zoneColor.get(r.destinationNum) ?? null) : null,
      groupNum: r.groupNum ?? null,
      team: { id: r.competitor.id, name: r.competitor.name, logo: teamLogo(r.competitor.id, r.competitor.imageVersion, 32) },
      played: r.gamePlayed ?? 0,
      won: r.gamesWon ?? 0,
      draw: r.gamesEven ?? 0,
      lost: r.gamesLost ?? 0,
      goalsFor: r.for ?? 0,
      goalsAgainst: r.against ?? 0,
      diff: r.ratio ?? 0,
      points: r.points ?? 0,
      form: (r.detailedRecentForm ?? []).slice(0, 5).map((g) => ({ outcome: g.outcome ?? -1, gameId: g.id ?? null })),
    }));
    const zones: StandingsZone[] = (group.destinations ?? [])
      .filter((d) => d.color && d.name)
      .map((d) => ({ name: d.name as string, color: d.color as string }));
    // مجموعات البطولة (كأس عالم/مجموعات) — num→اسم «المجموعة أ». فارغة للدوريات أحاديّة الجدول ⇒ عرض مسطّح.
    const groups: StandingsGroup[] = (group.groups ?? [])
      .filter((g) => g.name)
      .map((g) => ({ num: g.num, name: g.name as string }));
    return {
      competition: {
        id: comp?.id ?? competitionId,
        name: comp?.name ?? '',
        logo: comp ? competitionLogo(comp.id, comp.countryId, comp.imageVersion) : null,
      },
      rows,
      zones,
      groups,
    };
  } catch {
    return null;
  }
}

// الأبطال — كلّ موسم نهائيّه (مشاركان باسم؛ المواسم القديمة بلا أسماء تُستبعَد). الفائز = isQualified. لا تلفيق.
export async function getCompetitionHistory(id: number): Promise<ChampionRow[]> {
  if (!Number.isInteger(id) || id <= 0) return [];
  try {
    // appTypeId=5 (لا 3) عمداً: هو وحده يُعيد `group.games`/`secondaryTitle`/`entityId` (مفحوص حيًّا؛ appTypeId=3 يُعيد صفوفاً عارية).
    const res = await fetch(`${BASE}/competitions/history/?appTypeId=5&langId=27&timezoneName=Asia/Amman&userCountryId=6&competitions=${id}`, {
      signal: AbortSignal.timeout(6000),
      next: { revalidate: 86400, tags: ['sport-stats'] },
    });
    if (!res.ok) return [];
    const parsed = HistoryResponse.safeParse(await res.json());
    if (!parsed.success) return [];
    const info = new Map<number, { name: string | null; version: number | null }>();
    for (const c of parsed.data.competitors ?? []) info.set(c.id, { name: c.name ?? null, version: c.imageVersion ?? null });
    const rows: ChampionRow[] = [];
    for (const r of parsed.data.table?.rows ?? []) {
      const winnerId = r.entityId ?? null;
      if (winnerId == null) continue; // بلا بطل ⇒ صفّ غير صالح (لا تلفيق)
      const part = (r.group?.participants ?? []).find((p) => p.competitorId === winnerId);
      const name = part?.name ?? info.get(winnerId)?.name ?? null;
      const finalGameId = r.group?.games?.[0]?.gameId ?? r.group?.games?.[0]?.game?.id ?? null;
      rows.push({
        seasonNum: r.seasonNum ?? 0,
        title: r.title ?? null,
        winner: { id: winnerId, name, logo: teamLogo(winnerId, info.get(winnerId)?.version ?? null) },
        result: r.secondaryTitle ?? null,
        finalGameId,
      });
    }
    return rows;
  } catch {
    return [];
  }
}
