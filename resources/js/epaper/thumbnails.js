// Lazy thumbnail strip for the epaper reader. Thumbnails render on demand via
// IntersectionObserver (never all upfront) to bound memory on large issues.

const THUMB_WIDTH = 120; // CSS px target width; backing store scaled by DPR (capped)

export class Thumbnails {
  constructor(panel, pdfDoc, numPages, onSelect) {
    this.panel = panel;
    this.pdfDoc = pdfDoc;
    this.numPages = numPages;
    this.onSelect = onSelect;
    this.buttons = [];
    this.rendered = new Set();
    this.active = 1;
    this.observer = null;
  }

  build() {
    this.panel.replaceChildren();
    this.buttons = [];
    for (let n = 1; n <= this.numPages; n++) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ep-thumb';
      btn.dataset.page = String(n);

      const ph = document.createElement('span');
      ph.className = 'ep-thumb-ph';
      ph.style.aspectRatio = '1 / 1.414'; // A-series placeholder until real size known
      const num = document.createElement('span');
      num.className = 'ep-thumb-num';
      num.textContent = String(n);

      btn.append(ph, num);
      btn.addEventListener('click', () => this.onSelect(n));
      this.panel.append(btn);
      this.buttons.push(btn);
    }

    this.observer = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (entry.isIntersecting) {
            const n = Number(entry.target.dataset.page);
            void this._renderThumb(entry.target, n);
            this.observer.unobserve(entry.target);
          }
        }
      },
      { root: this.panel, rootMargin: '200px 0px' },
    );
    this.buttons.forEach((b) => this.observer.observe(b));
    this.setActive(this.active);
  }

  /** Re-trigger observation (e.g. when the panel becomes visible). */
  refresh() {
    this.buttons.forEach((b) => {
      if (!this.rendered.has(Number(b.dataset.page))) this.observer.observe(b);
    });
  }

  async _renderThumb(btn, n) {
    if (this.rendered.has(n)) return;
    this.rendered.add(n);
    try {
      const page = await this.pdfDoc.getPage(n);
      const base = page.getViewport({ scale: 1 });
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      const scale = THUMB_WIDTH / base.width;
      const vp = page.getViewport({ scale: scale * dpr });
      const canvas = document.createElement('canvas');
      canvas.width = Math.floor(vp.width);
      canvas.height = Math.floor(vp.height);
      canvas.style.width = `${THUMB_WIDTH}px`;
      canvas.style.height = `${Math.floor(base.height * scale)}px`;
      await page.render({ canvasContext: canvas.getContext('2d'), viewport: vp }).promise;
      const ph = btn.querySelector('.ep-thumb-ph');
      if (ph) ph.replaceWith(canvas);
    } catch {
      this.rendered.delete(n); // allow a retry on next refresh
    }
  }

  setActive(n) {
    this.active = n;
    this.buttons.forEach((b) => {
      const isActive = Number(b.dataset.page) === n;
      b.classList.toggle('is-active', isActive);
      if (isActive) b.scrollIntoView({ block: 'nearest' });
    });
  }
}
