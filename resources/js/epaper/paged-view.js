// Paged rendering: one page ('single') or an RTL-aware pair ('spread'). Only the
// current page/spread is rendered (memory-light). Spread pairs [1,2],[3,4]…; the
// flex row inherits dir from <html>, so in RTL the lower page sits on the right.

import { clamp, renderPageInto, isCancel } from './util.js';

const MIN_SCALE = 0.25;
const MAX_SCALE = 6;
const GAP = 16;

export class PagedView {
  constructor(ctx, spread) {
    this.ctx = ctx;
    this.spread = spread;
    this.onPage = () => {};
    this.tasks = [];
    this.token = 0;
    this.wrap = null;
  }

  mount() {
    this.ctx.stage.replaceChildren();
    this.ctx.stage.classList.add('ep-stage--paged');
    this.ctx.stage.classList.toggle('ep-stage--spread', this.spread);
    this.wrap = document.createElement('div');
    this.wrap.className = 'ep-pagewrap';
    // اتجاه صريح (لا اعتماد على الوراثة) — ترتيب الصفحتين في الوضع المزدوج صحيح RTL
    // حتى لو وُضِع القارئ في شجرة LTR فرعيّة.
    this.wrap.style.direction = document.documentElement.dir === 'rtl' ? 'rtl' : 'ltr';
    this.ctx.stage.append(this.wrap);
  }

  destroy() {
    this._cancel();
    this.ctx.stage.classList.remove('ep-stage--paged', 'ep-stage--spread');
    this.ctx.stage.replaceChildren();
    this.wrap = null;
  }

  currentPage() {
    return this.ctx.page;
  }

  _pagesFor(n) {
    if (!this.spread) return [n];
    const start = n % 2 === 1 ? n : n - 1;
    const pair = [start];
    if (start + 1 <= this.ctx.numPages) pair.push(start + 1);
    return pair;
  }

  async goTo(n) {
    this.ctx.page = clamp(Math.round(n), 1, this.ctx.numPages);
    await this.render();
    this.onPage(this.ctx.page);
  }

  async relayout() {
    await this.render();
  }

  _cancel() {
    this.tasks.forEach((t) => {
      try { t.cancel(); } catch { /* ignore */ }
    });
    this.tasks = [];
  }

  _scale(base, slots) {
    if (this.ctx.fitMode === 'custom') return this.ctx.manualScale;
    const availW = this.ctx.availWidth() - GAP * (slots - 1);
    const widthScale = availW / (base.width * slots);
    if (this.ctx.fitMode === 'page') {
      return Math.min(widthScale, this.ctx.availHeight() / base.height);
    }
    return widthScale;
  }

  async render() {
    if (!this.ctx.pdfDoc || !this.wrap) return;
    const token = ++this.token;
    const pages = this._pagesFor(this.ctx.page);
    this._cancel();

    const objs = await Promise.all(pages.map((p) => this.ctx.pdfDoc.getPage(p)));
    if (token !== this.token) return;

    const base = objs[0].getViewport({ scale: 1 });
    const scale = clamp(this._scale(base, objs.length), MIN_SCALE, MAX_SCALE);
    this.ctx.appliedScale = scale;

    this.wrap.replaceChildren();
    // كل لوحة داخل غلاف نسبيّ — يتيح إسقاط طبقة تظليل البحث (Phase 6) فوقها بدقّة.
    const canvases = [];
    const wraps = [];
    for (let i = 0; i < objs.length; i++) {
      const cw = document.createElement('div');
      cw.className = 'ep-canvas-wrap';
      const c = document.createElement('canvas');
      c.className = 'ep-canvas';
      cw.append(c);
      this.wrap.append(cw);
      canvases.push(c);
      wraps.push(cw);
    }

    const highlighter = this.ctx.highlighter;
    for (let i = 0; i < objs.length; i++) {
      const { task } = renderPageInto(objs[i], scale, canvases[i]);
      this.tasks.push(task);
      try {
        await task.promise;
      } catch (e) {
        if (!isCancel(e)) { /* leave the page blank rather than throw */ }
      }
      if (token !== this.token) return;

      // تظليل تطابقات البحث (إن وُصِل القارئ عبر ?q ووُجدت طبقة نصّ). أفضل-جهد.
      if (highlighter && highlighter.active) {
        await highlighter.render(objs[i], scale, wraps[i]);
        if (token !== this.token) return;
      }
    }
  }
}
