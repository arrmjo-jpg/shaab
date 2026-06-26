// طبقة قراءة محايدة مشتركة (أخبار/مقالات/تغطية خاصة/صفحات نصّيّة) — استخراج عناوين h2/h3
// لجدول المحتوى + المرابط، مع حقن ids مستقرّة خادميًّا (لا حقن DOM هشّ على العميل). دالّة نقيّة
// لأيّ HTML مُعقَّم؛ لا تبعيّة على pages ولا articles (Reuse-First / Zero-Parallel).

export interface Heading {
  id: string;
  text: string;
  level: 2 | 3;
}

function slugifyHeading(text: string, index: number): string {
  const base = text
    .toLowerCase()
    .replace(/[^\p{L}\p{N}\s-]/gu, '')
    .trim()
    .replace(/\s+/g, '-');
  return base ? `${base}-${index}` : `section-${index}`;
}

export function extractHeadings(html: string): { html: string; headings: Heading[] } {
  const headings: Heading[] = [];
  let index = 0;
  const out = html.replace(
    /<(h2|h3)((?:\s[^>]*)?)>([\s\S]*?)<\/\1>/gi,
    (_match, tag: string, attrs: string, inner: string) => {
      const text = inner.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
      if (!text) return `<${tag}${attrs}>${inner}</${tag}>`;
      const level: 2 | 3 = tag.toLowerCase() === 'h2' ? 2 : 3;
      const id = slugifyHeading(text, index);
      index += 1;
      headings.push({ id, text, level });
      // نزع أيّ id موجود ثمّ حقن المُولَّد (ضمان تطابق وحيد مع جدول المحتوى).
      const cleaned = attrs.replace(/\sid\s*=\s*("[^"]*"|'[^']*'|[^\s>]+)/gi, '');
      return `<${tag}${cleaned} id="${id}">${inner}</${tag}>`;
    },
  );
  return { html: out, headings };
}
