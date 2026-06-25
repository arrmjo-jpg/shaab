// Reader search highlighting (Phase 6). When the reader is opened via a deep link
// carrying ?q=<term> (from archive search), text matching the term on the rendered
// page is highlighted in place. Positioning is delegated entirely to pdf.js's
// official TextLayer (it owns the RTL / rotation / font-metric math) — no manual
// bbox arithmetic, no brittle hacks. Pages without an embedded/OCR text layer
// (scanned images) simply yield zero matches; the reader's search pill is the
// graceful fallback that always conveys the active term regardless.

import { TextLayer, setLayerDimensions } from 'pdfjs-dist/build/pdf.min.mjs';

const MIN_QUERY = 2;

export class Highlighter {
  constructor(query) {
    this.query = (query || '').trim();
    this.active = this.query.length >= MIN_QUERY;
    this.anchored = false; // مرّة واحدة: مرّر أوّل تطابق إلى المنظور عند أوّل عرض
  }

  /**
   * يبني طبقة نصّ pdf.js فوق اللوحة المُعطاة ويظلّل العناصر المطابِقة. أفضل-جهد:
   * لا يرمي أبداً — أيّ تعذّر يترك اللوحة كما هي (والحبّة هي البديل اللطيف).
   * @returns {Promise<number>} عدد العناصر المطابِقة على هذه الصفحة
   */
  async render(page, scale, wrapEl) {
    if (!this.active || !wrapEl || !page) return 0;

    try {
      const viewport = page.getViewport({ scale });

      const layer = document.createElement('div');
      layer.className = 'ep-textlayer';
      // عقد مقاس pdf.js: العرض/الارتفاع = ‎--total-scale-factor × الأبعاد الخام،
      // وحجم خطّ كل عنصر = ‎--total-scale-factor × ‎--font-height. فلا بدّ من ضبطها.
      layer.style.setProperty('--total-scale-factor', String(scale));
      layer.style.setProperty('--scale-round-x', '1px');
      layer.style.setProperty('--scale-round-y', '1px');
      setLayerDimensions(layer, viewport);
      wrapEl.append(layer);

      const textContent = await page.getTextContent();
      const tl = new TextLayer({ textContentSource: textContent, container: layer, viewport });
      await tl.render();

      const q = this.query.toLowerCase();
      const divs = tl.textDivs || [];
      const strs = tl.textContentItemsStr || [];
      let matched = 0;
      let first = null;
      for (let i = 0; i < divs.length; i++) {
        const s = (strs[i] || '').toLowerCase();
        if (s && s.indexOf(q) !== -1) {
          divs[i].classList.add('ep-hl');
          if (!first) first = divs[i];
          matched++;
        }
      }

      // مرّر أوّل تطابق إلى المنظور مرّة واحدة فقط (عند أوّل عرض) كي لا يُقفز التمرير
      // مع كل إعادة تخطيط (تكبير/تبديل وضع).
      if (first && !this.anchored) {
        this.anchored = true;
        try {
          first.scrollIntoView({ block: 'center', inline: 'center' });
        } catch {
          /* ignore */
        }
      }

      return matched;
    } catch {
      return 0; // أفضل-جهد: لا تظليل ⇒ تبقى الحبّة هي الإشارة اللطيفة
    }
  }
}
