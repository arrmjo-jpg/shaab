// مُحمِّل pdf.js (عميل فقط): استيراد ديناميكيّ يُبقي المكتبة خارج حزمة الخادم/التحميل الأوّل،
// ويضبط العامل المُستضاف ذاتيّاً مرّة واحدة (موثوق عبر webpack/Next بلا اعتماد CDN للعامل).
// مُشترك بين كلّ مكوّنات القارئ كي تُحمَّل المكتبة + العامل نسخةً واحدة.
let cached: Promise<typeof import('pdfjs-dist')> | null = null;

export function loadPdfjs(): Promise<typeof import('pdfjs-dist')> {
  cached ??= import('pdfjs-dist').then((pdfjs) => {
    pdfjs.GlobalWorkerOptions.workerSrc = '/pdf.worker.min.mjs';
    return pdfjs;
  });
  return cached;
}

// cMaps + خطوط احتياطيّة — تُستعمَل فقط حين يشير الـ PDF لخطوط/ترميزات غير مضمَّنة (نادر في
// صحفنا ذات النصّ المضمَّن). مثبّتة بإصدار pdfjs‑dist نفسه لتفادي انجراف النسخة.
export const PDF_CMAP_URL = 'https://unpkg.com/pdfjs-dist@5.7.284/cmaps/';
export const PDF_STANDARD_FONTS_URL = 'https://unpkg.com/pdfjs-dist@5.7.284/standard_fonts/';
