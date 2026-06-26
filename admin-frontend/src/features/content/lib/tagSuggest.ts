import type { ContentLocale } from '@/types/content.types';

/** Recursively collect text nodes from a TipTap JSON document. */
export function tiptapText(doc: unknown): string {
  const out: string[] = [];
  const walk = (node: unknown): void => {
    if (!node || typeof node !== 'object') return;
    const n = node as { text?: unknown; content?: unknown };
    if (typeof n.text === 'string') out.push(n.text);
    if (Array.isArray(n.content)) n.content.forEach(walk);
  };
  walk(doc);
  return out.join(' ');
}

// Small stop-word lists — enough to suppress the most common noise.
const AR_STOP = new Set([
  'في', 'من', 'على', 'إلى', 'الى', 'عن', 'أن', 'ان', 'إن', 'هذا', 'هذه', 'ذلك', 'التي',
  'الذي', 'الذين', 'ما', 'لا', 'مع', 'هو', 'هي', 'هم', 'عند', 'بعد', 'قبل', 'كل', 'بين',
  'حتى', 'أي', 'كما', 'لكن', 'غير', 'عبر', 'نحو', 'منذ', 'خلال', 'حول', 'قد', 'كان',
  'كانت', 'يكون', 'ثم', 'أو', 'او', 'وقد', 'كذلك', 'حيث', 'إذا', 'اذا', 'لقد', 'ضد',
]);

const EN_STOP = new Set([
  'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'with', 'is', 'are',
  'was', 'were', 'be', 'by', 'at', 'from', 'this', 'that', 'it', 'as', 'has', 'have',
  'will', 'not', 'but', 'they', 'their', 'its', 'into', 'over', 'after', 'before',
]);

const AR_PREFIXES = ['ال', 'وال', 'بال', 'فال', 'كال', 'لل'];

/** Strip a leading Arabic definite-article prefix for grouping (ال…). */
function arabicRoot(token: string): string {
  for (const p of AR_PREFIXES) {
    if (token.startsWith(p) && token.length - p.length >= 3) {
      return token.slice(p.length);
    }
  }
  return token;
}

/**
 * Suggest tag candidates from free text (title + subtitle + body), Arabic-aware.
 * Frequency-ranked single tokens, stop-words and short tokens removed, and
 * already-selected tags excluded. Pure suggestion — never auto-applies.
 */
export function suggestTags(
  text: string,
  locale: ContentLocale,
  existing: ReadonlyArray<string> = [],
  limit = 8,
): string[] {
  if (!text.trim()) return [];

  const taken = new Set(existing.map((t) => t.toLowerCase()));
  const counts = new Map<string, { display: string; count: number }>();

  // Split on anything that isn't a Unicode letter or number.
  const tokens = text.split(/[^\p{L}\p{N}]+/u).filter(Boolean);

  for (const raw of tokens) {
    const lower = raw.toLowerCase();
    const isArabic = /[؀-ۿ]/.test(raw);
    const minLen = isArabic ? 3 : 4;
    if (lower.length < minLen) continue;
    if (isArabic ? AR_STOP.has(lower) : EN_STOP.has(lower)) continue;
    if (/^\d+$/.test(lower)) continue;

    const key = isArabic ? arabicRoot(lower) : lower;
    const entry = counts.get(key);
    if (entry) {
      entry.count += 1;
    } else {
      // Display the original-cased token (first occurrence).
      counts.set(key, { display: isArabic ? arabicRoot(raw) : raw, count: 1 });
    }
  }

  return [...counts.values()]
    .sort((a, b) => b.count - a.count || a.display.localeCompare(b.display, locale))
    .map((e) => e.display)
    .filter((t) => !taken.has(t.toLowerCase()))
    .slice(0, limit);
}
