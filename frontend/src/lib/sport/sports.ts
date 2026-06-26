// مصدر واحد لرياضات /sport (DRY) — يقود المنيو والتوجيه وطبقة البيانات. معرّفات 365 الرسميّة (مؤكَّدة من `web/sports/`).
// كرة القدم = الافتراضيّة بلا بادئة (/sport)؛ الباقي ببادئة (/sport/{slug}). الكريكيت غير متاح في 365 ⇒ غير مُدرَج.
export interface SportDef {
  key: string;
  slug: string; // = nameForURL في 365 (basketball/tennis/…)
  label: string;
  sportId: number; // sports={id} في API
}

export const SPORTS: readonly SportDef[] = [
  { key: 'football', slug: 'football', label: 'كرة القدم', sportId: 1 },
  { key: 'basketball', slug: 'basketball', label: 'كرة السلة', sportId: 2 },
  { key: 'tennis', slug: 'tennis', label: 'التنس', sportId: 3 },
  { key: 'handball', slug: 'handball', label: 'كرة اليد', sportId: 5 },
  { key: 'volleyball', slug: 'volleyball', label: 'الكرة الطائرة', sportId: 8 },
];

export const DEFAULT_SPORT = SPORTS[0]; // كرة القدم

/** مسار الرياضة: الافتراضيّة /sport؛ غيرها /sport/{slug}. */
export function sportHref(s: SportDef): string {
  return s.key === DEFAULT_SPORT.key ? '/sport' : `/sport/${s.slug}`;
}

export function findSportBySlug(slug: string): SportDef | undefined {
  return SPORTS.find((s) => s.slug === slug);
}
