import 'server-only';
import { cache } from 'react';
import { z } from 'zod';

// بيانات بورصة عمّان (ASE) — المصدر الرسميّ الوحيد: واجهة الشريط `ticker_feeds` (JSON).
// كلّ عنصر: object_id, name_short/long, current_price ("2,201.63"), current_variation_percent ("-0.61"/"0"),
// group_code ("20" = مؤشّرات ASE20/ASETR). صفر تلفيق: أيّ فشل/فراغ ⇒ null ⇒ حالة فارغة أنيقة. ISR 120s + مهلة.
const ASE_TICKER_URL = 'https://www.ase.com.jo/ar/ticker_feeds';

export type AseDir = 'up' | 'down' | 'flat';

export interface AseTickerItem {
  symbol: string; // object_id (ASE20, AAIN…)
  name: string; // name_short (يقع على name_long ثمّ symbol)
  price: string; // current_price كما ورد (منسّق بفواصل آلاف)
  changePct: number; // النسبة المئويّة المُحلَّلة
  dir: AseDir; // اتّجاه التغيّر (موجب/سالب/صفر)
  isIndex: boolean; // group_code === '20'
}

interface RawFeed {
  object_id?: string;
  name_short?: string;
  name_long?: string;
  current_price?: string;
  current_variation_percent?: string;
  group_code?: string;
}

async function fetchAseTicker(): Promise<AseTickerItem[] | null> {
  try {
    const res = await fetch(ASE_TICKER_URL, {
      headers: { 'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', Accept: 'application/json' },
      signal: AbortSignal.timeout(5000),
      next: { revalidate: 120, tags: ['ase-ticker'] },
    });
    if (!res.ok) return null;

    const raw: unknown = await res.json();
    if (!Array.isArray(raw)) return null;

    const items: AseTickerItem[] = (raw as RawFeed[])
      .map((r) => {
        const symbol = (r.object_id ?? '').trim();
        const name = (r.name_short || r.name_long || symbol).trim();
        const price = (r.current_price ?? '').trim();
        const priceNum = Number.parseFloat(price.replace(/,/g, ''));
        const pct = Number.parseFloat((r.current_variation_percent ?? '').replace(/[^0-9.+-]/g, ''));
        return { symbol, name, price, priceNum, pct, group: (r.group_code ?? '').trim() };
      })
      // استبعِد ما لا سعر فعليّاً له (لم يُتداوَل ⇒ لا بيانات سوق؛ ليس تلفيقاً، بل حذف فراغ).
      .filter((x) => x.symbol && x.name && Number.isFinite(x.priceNum) && x.priceNum > 0)
      .map((x) => {
        const changePct = Number.isFinite(x.pct) ? x.pct : 0;
        const dir: AseDir = changePct > 0 ? 'up' : changePct < 0 ? 'down' : 'flat';
        return { symbol: x.symbol, name: x.name, price: x.price, changePct, dir, isIndex: x.group === '20' };
      });

    if (items.length === 0) return null;

    // المؤشّرات أوّلاً (ASE20/ASETR) ثمّ بقيّة الأسهم حسب ترتيب الـAPI.
    const indices = items.filter((i) => i.isIndex);
    const stocks = items.filter((i) => !i.isIndex);
    return [...indices, ...stocks];
  } catch {
    return null;
  }
}

export const getAseTicker = cache(fetchAseTicker);

// ============================================================================
// لوحة بورصة عمّان (/bourse) — مصادر ASE الرسميّة (ملخّص + مؤشّر حيّ + رابحون/خاسرون).
// نفس نمط الشريط أعلاه: headers + AbortSignal.timeout + ISR 300s + null عند الفشل
// (عزل فشل لكلّ بلوك على حدة، صفر تلفيق — حالة فارغة صادقة).
// ============================================================================

const ASE_HEADERS = {
  'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
  Accept: 'application/json, text/plain, */*',
  Referer: 'https://www.ase.com.jo/ar',
} as const;

// قيمة ASE قد تَرِد نصّاً بفواصل آلاف ("22,606,221") أو رقماً ⇒ تحليل آمن (غير صالح ⇒ 0).
function aseNum(v: unknown): number {
  const n = Number.parseFloat(String(v ?? '').replace(/[^\d.-]/g, ''));
  return Number.isFinite(n) ? n : 0;
}

const numish = z.union([z.string(), z.number()]).nullish();

// ---------- (1) ملخّص السوق: get_daily_live_summary ----------
export interface AseSegmentStats {
  tradingValue: number;
  tradingVolume: number;
  transactions: number;
  securities: number;
}
export interface AseBreadth {
  gainers: number;
  losers: number;
  unchanged: number;
}
export interface AseSegment {
  stats: AseSegmentStats;
  breadth: AseBreadth | null; // null للسندات (لا توزيع رابح/خاسر في المصدر)
}
export interface AseActiveStock {
  symbol: string;
  price: number;
  valueTraded: number;
}
export interface AseSummary {
  date: string | null;
  regular: AseSegment;
  otc: AseSegment;
  bonds: AseSegment;
  mostActive: AseActiveStock[];
}

const SummaryRaw = z
  .object({
    data: z
      .object({
        date: z.string().nullish(),
        top_gainers: numish,
        top_losers: numish,
        un_changed: numish,
        trading_value: numish,
        trading_volume: numish,
        number_of_transactions: numish,
        number_of_securities: numish,
        top_gainers_otc: numish,
        top_losers_otc: numish,
        un_changed_otc: numish,
        trading_value_otc: numish,
        trading_volume_otc: numish,
        otc_transactions: numish,
        otc_securities: numish,
        trading_value_bonds: numish,
        trading_volume_bonds: numish,
        bonds_transactions: numish,
        bonds_securities: numish,
        most_active_shares: z
          .array(
            z
              .object({ object_id: z.string().nullish(), current_price: numish, total_market_value_traded: numish })
              .passthrough(),
          )
          .nullish(),
      })
      .passthrough()
      .nullish(),
  })
  .passthrough();

export const getAseSummary = cache(async (): Promise<AseSummary | null> => {
  try {
    const res = await fetch('https://www.ase.com.jo/ar/summary/get_daily_live_summary', {
      headers: ASE_HEADERS,
      signal: AbortSignal.timeout(5000),
      next: { revalidate: 300, tags: ['ase-summary'] },
    });
    if (!res.ok) return null;
    const parsed = SummaryRaw.safeParse(await res.json());
    if (!parsed.success || !parsed.data.data) return null;
    const d = parsed.data.data;
    return {
      date: d.date ?? null,
      regular: {
        stats: {
          tradingValue: aseNum(d.trading_value),
          tradingVolume: aseNum(d.trading_volume),
          transactions: aseNum(d.number_of_transactions),
          securities: aseNum(d.number_of_securities),
        },
        breadth: { gainers: aseNum(d.top_gainers), losers: aseNum(d.top_losers), unchanged: aseNum(d.un_changed) },
      },
      otc: {
        stats: {
          tradingValue: aseNum(d.trading_value_otc),
          tradingVolume: aseNum(d.trading_volume_otc),
          transactions: aseNum(d.otc_transactions),
          securities: aseNum(d.otc_securities),
        },
        breadth: {
          gainers: aseNum(d.top_gainers_otc),
          losers: aseNum(d.top_losers_otc),
          unchanged: aseNum(d.un_changed_otc),
        },
      },
      bonds: {
        stats: {
          tradingValue: aseNum(d.trading_value_bonds),
          tradingVolume: aseNum(d.trading_volume_bonds),
          transactions: aseNum(d.bonds_transactions),
          securities: aseNum(d.bonds_securities),
        },
        breadth: null,
      },
      mostActive: (d.most_active_shares ?? [])
        .map((s) => ({
          symbol: (s.object_id ?? '').trim(),
          price: aseNum(s.current_price),
          valueTraded: aseNum(s.total_market_value_traded),
        }))
        .filter((s) => s.symbol),
    };
  } catch {
    return null;
  }
});

// ---------- (2) مؤشّر السوق الحيّ: charts/live_market ----------
export interface AseIndexPoint {
  t: number; // طابع زمنيّ (ms)
  v: number; // قيمة المؤشّر
}
export interface AseIndexData {
  series: AseIndexPoint[];
  current: number; // آخر قيمة
  open: number; // أوّل قيمة (افتتاح الجلسة)
  changePct: number; // (الحاليّة − الافتتاح) / الافتتاح %
  dir: AseDir;
}

const IndexRaw = z.array(z.array(z.number()));

export const getAseIndexSeries = cache(async (): Promise<AseIndexData | null> => {
  try {
    const res = await fetch('https://www.ase.com.jo/ar/charts/live_market?_format=json', {
      headers: ASE_HEADERS,
      signal: AbortSignal.timeout(5000),
      next: { revalidate: 300, tags: ['ase-index'] },
    });
    if (!res.ok) return null;
    const parsed = IndexRaw.safeParse(await res.json());
    if (!parsed.success) return null;
    const series = parsed.data
      .filter((p) => p.length >= 2 && Number.isFinite(p[0]) && Number.isFinite(p[1]))
      .map((p) => ({ t: p[0], v: p[1] }));
    if (series.length === 0) return null;
    const open = series[0].v;
    const current = series[series.length - 1].v;
    const changePct = open > 0 ? ((current - open) / open) * 100 : 0;
    const dir: AseDir = changePct > 0 ? 'up' : changePct < 0 ? 'down' : 'flat';
    return { series, current, open, changePct, dir };
  } catch {
    return null;
  }
});

// ---------- (3) الرابحون/الخاسرون: api/v1/advancers | decliners ----------
export interface AseMover {
  symbol: string;
  price: number;
  changePct: number;
  openPrice: number;
  dir: AseDir;
}

const MoversRaw = z.array(
  z
    .object({ object_id: z.string().nullish(), current_price: numish, current_variation_percent: numish, open_price: numish })
    .passthrough(),
);

async function fetchMovers(path: 'advancers' | 'decliners'): Promise<AseMover[] | null> {
  try {
    const res = await fetch(`https://www.ase.com.jo/ar/api/v1/${path}`, {
      headers: ASE_HEADERS,
      signal: AbortSignal.timeout(5000),
      next: { revalidate: 300, tags: ['ase-movers'] },
    });
    if (!res.ok) return null;
    const parsed = MoversRaw.safeParse(await res.json());
    if (!parsed.success) return null;
    const movers = parsed.data
      .map((r) => {
        const pct = aseNum(r.current_variation_percent);
        return {
          symbol: (r.object_id ?? '').trim(),
          price: aseNum(r.current_price),
          changePct: pct,
          openPrice: aseNum(r.open_price),
          dir: (pct > 0 ? 'up' : pct < 0 ? 'down' : 'flat') as AseDir,
        };
      })
      .filter((m) => m.symbol);
    return movers.length ? movers : null;
  } catch {
    return null;
  }
}

export const getAseAdvancers = cache((): Promise<AseMover[] | null> => fetchMovers('advancers'));
export const getAseDecliners = cache((): Promise<AseMover[] | null> => fetchMovers('decliners'));

// ---------- (4) التعاميم والإفصاحات: لا JSON API (404/406) ⇒ قراءة صفحتَي ASE الرسميّتين (HTML).
// scraping متسامح يعتمد بنية Drupal views (views-row / document-name / published / filename[-zip]).
// أيّ تغيير في بنية ASE ⇒ صفّ ناقص يُتجاوَز أو null (حالة فارغة صادقة، لا تلفيق). روابط التحميل مطلقة لـASE.
const ASE_ORIGIN = 'https://www.ase.com.jo';

export interface AseDoc {
  name: string;
  date: string;
  pdfUrl: string | null;
  zipUrl: string | null;
}

function aseAbs(href: string | null | undefined): string | null {
  if (!href) return null;
  return href.startsWith('http') ? href : `${ASE_ORIGIN}${href}`;
}

function decodeEntities(s: string): string {
  return s
    .replace(/<[^>]+>/g, '')
    .replace(/&amp;/g, '&')
    .replace(/&lt;/g, '<')
    .replace(/&gt;/g, '>')
    .replace(/&quot;/g, '"')
    .replace(/&#0?39;/g, "'")
    .replace(/&nbsp;/g, ' ')
    .replace(/\s+/g, ' ')
    .trim();
}

function scrapeAseDocs(html: string): AseDoc[] {
  const docs: AseDoc[] = [];
  // صفحتا /disclosures و/circulars جداول HTML بصفوف <tr>، لكنّهما تختلفان في موضع رابط التحميل:
  //  • الإفصاحات: الاسمُ نصّ مباشر، ورابط PDF في خليّة `views-field-filename` منفصلة (+ZIP أحياناً).
  //  • التعاميم: الاسمُ داخل <a href="رابط التحميل">…</a> في خليّة الاسم نفسها (لا خليّة filename).
  // فنُعالج الحالتين: نأخذ نصّ خليّة الاسم (بعد نزع الوسوم)، والرابط من filename وإلّا من رابط خليّة الاسم.
  // صفّ الترويسة <th> يُتجاوَز (لا `</td>` في خلاياه). أيّ صفّ بلا اسم ⇒ يُتجاوَز (لا تلفيق).
  const parts = html.split(/<tr[\s>]/).slice(1);
  for (const raw of parts) {
    const chunk = raw.slice(0, 2000); // حدّ آمن لصفّ واحد
    const cellMatch = chunk.match(/views-field-document-name"[^>]*>([\s\S]*?)<\/td>/);
    if (!cellMatch) continue;
    const name = decodeEntities(cellMatch[1]);
    if (!name) continue;
    const fileLink = chunk.match(/views-field-filename"[^>]*>\s*<a href="([^"]+)"/);
    const cellLink = cellMatch[1].match(/<a href="([^"]+)"/);
    const zipMatch = chunk.match(/views-field-filename-zip"[^>]*>\s*<a href="([^"]+)"/);
    const dateMatch = chunk.match(/views-field-published"[^>]*>\s*([^<]+?)\s*</);
    docs.push({
      name,
      date: dateMatch ? decodeEntities(dateMatch[1]) : '',
      pdfUrl: aseAbs(fileLink?.[1] ?? cellLink?.[1] ?? null),
      zipUrl: aseAbs(zipMatch?.[1]),
    });
  }
  return docs;
}

async function fetchAseDocs(path: 'disclosures' | 'circulars'): Promise<AseDoc[] | null> {
  try {
    const res = await fetch(`${ASE_ORIGIN}/ar/${path}`, {
      headers: { ...ASE_HEADERS, Accept: 'text/html,application/xhtml+xml,*/*' },
      signal: AbortSignal.timeout(9000), // صفحتا HTML ~200KB، أبطأ من JSON ⇒ مهلة أوسع
      next: { revalidate: 300, tags: ['ase-docs'] },
    });
    if (!res.ok) return null;
    const docs = scrapeAseDocs(await res.text()).slice(0, 30); // أحدث 30 (الجدول قابل للتمرير)
    return docs.length ? docs : null;
  } catch {
    return null;
  }
}

export const getAseDisclosures = cache((): Promise<AseDoc[] | null> => fetchAseDocs('disclosures'));
export const getAseCirculars = cache((): Promise<AseDoc[] | null> => fetchAseDocs('circulars'));
