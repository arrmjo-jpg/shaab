/**
 * مولّد slug يطابق `Article::arabicSlug` على الـ backend ومرجع SlugField:
 *   - lowercase
 *   - يستبدل المسافات بـ -
 *   - يحتفظ بأحرف يونيكود (يشمل العربي) والأرقام
 *   - يُنظّف - الزائدة من الطرفين والمتجاورة
 */
export function autoSlug(title: string): string {
  const trimmed = title.trim().toLowerCase();
  if (trimmed === '') return '';
  return trimmed
    .replace(/\s+/gu, '-')
    .replace(/[^\p{L}\p{N}-]+/gu, '')
    .replace(/^-+|-+$/gu, '')
    .replace(/-+/g, '-');
}

/** نمط slug المقبول على الـ backend (regex Laravel نفسه). */
export const SLUG_REGEX = /^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u;

/** نقحرة عربيّ→لاتينيّ مبسّطة لتوليد مفاتيح ASCII من أسماء عربيّة. */
const AR_TO_LATIN: Record<string, string> = {
  ء: '', آ: 'a', أ: 'a', ؤ: 'w', إ: 'i', ئ: 'y', ا: 'a', ٱ: 'a',
  ب: 'b', ة: 'a', ت: 't', ث: 'th', ج: 'j', ح: 'h', خ: 'kh',
  د: 'd', ذ: 'th', ر: 'r', ز: 'z', س: 's', ش: 'sh', ص: 's', ض: 'd',
  ط: 't', ظ: 'z', ع: 'a', غ: 'gh', ف: 'f', ق: 'q', ك: 'k', ل: 'l',
  م: 'm', ن: 'n', ه: 'h', و: 'w', ى: 'a', ي: 'y',
  '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4',
  '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9',
};

/**
 * مفتاح تقنيّ `[a-z0-9_]` من اسم بشريّ (عربيّ أو لاتينيّ): إزالة التشكيل/التطويل، نقحرة
 * العربيّة، تصغير، استبدال غير المسموح بـ`_`، ثمّ قصّ الأطراف. لمفاتيح المساحات الإعلانيّة
 * التي تستهلكها الواجهة (لا تقبل اليونيكود ولا الشرطة، خلافًا لـ autoSlug).
 */
export function autoKey(name: string): string {
  const stripped = name.replace(/[ً-ْٰـ]/gu, '');
  const latin = [...stripped].map((ch) => AR_TO_LATIN[ch] ?? ch).join('');
  return latin
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '_')
    .replace(/^_+|_+$/g, '');
}

/** تنظيف إدخال يدويّ لمفتاح أثناء الكتابة (بلا قصّ الأطراف كي لا يمنع كتابة `_`). */
export function sanitizeKey(input: string): string {
  return input.toLowerCase().replace(/\s+/gu, '_').replace(/[^a-z0-9_]/g, '');
}
