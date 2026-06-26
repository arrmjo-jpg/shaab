/**
 * Build a short summary from the first N meaningful "lines" (top-level blocks) of a
 * TipTap document, so the editor never has to type an excerpt by hand. Mirrors the
 * rule: collect plain text per block, skip empty / punctuation-only lines, join the
 * first two, then cap at a word boundary. Arabic lives in the BMP, so JS string
 * length / slice operate per-character here (no surrogate splitting).
 */
export function excerptFromDoc(doc: unknown, maxLines = 2, target = 160): string {
  if (!doc || typeof doc !== 'object') return '';
  const blocks = (doc as { content?: unknown }).content;
  if (!Array.isArray(blocks)) return '';

  const lineText = (node: unknown): string => {
    const parts: string[] = [];
    const walk = (n: unknown): void => {
      if (!n || typeof n !== 'object') return;
      const x = n as { type?: unknown; text?: unknown; content?: unknown };
      if (typeof x.text === 'string') parts.push(x.text);
      else if (x.type === 'hardBreak') parts.push(' ');
      if (Array.isArray(x.content)) x.content.forEach(walk);
    };
    walk(node);
    return parts.join('').replace(/\s+/gu, ' ').trim();
  };

  const lines: string[] = [];
  for (const block of blocks) {
    if (lines.length >= maxLines) break;
    const line = lineText(block);
    if (line && /[\p{L}\p{N}]/u.test(line)) lines.push(line);
  }
  if (lines.length === 0) return '';

  const text = lines.join(' ');
  if (text.length <= target) return text;

  const slice = text.slice(0, target);
  const lastSpace = slice.lastIndexOf(' ');
  const cut = lastSpace >= target * 0.6 ? slice.slice(0, lastSpace) : slice;
  return `${cut.replace(/\s+$/u, '')}…`;
}
