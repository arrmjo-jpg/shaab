// Continuous (vertical scroll) rendering. Slots are laid out for every page using
// page 1's aspect (newspapers are uniform); each slot's canvas renders when it nears
// the viewport and is RELEASED when it scrolls far away — so memory stays bounded for
// arbitrarily large issues. Uniform width-based scale; 'page' fit collapses to width.

import { clamp, renderPageInto, isCancel, throttle } from './util.js';

const MIN_SCALE = 0.25;
const MAX_SCALE = 6;

export class ContinuousView {
  constructor(ctx) {
    this.ctx = ctx;
    this.onPage = () => {};
    this.slots = [];
    this.base = null;
    this.scale = 1;
    this.observer = null;
    this.column = null;
    this._lastWidth = 0; // لتجاوز إعادة التخطيط عند تغيّر الارتفاع فقط (شريط عنوان iOS)
    this._onScroll = throttle(() => this._updateCurrent(), 120);
  }

  async mount() {
    this.ctx.stage.replaceChildren();
    this.ctx.stage.classList.add('ep-stage--continuous');

    const first = await this.ctx.pdfDoc.getPage(1);
    this.base = first.getViewport({ scale: 1 });
    this.scale = clamp(this._fitScale(), MIN_SCALE, MAX_SCALE);
    this.ctx.appliedScale = this.scale;
    this._lastWidth = this.ctx.availWidth();

    this.column = document.createElement('div');
    this.column.className = 'ep-column';
    this.ctx.stage.append(this.column);

    for (let n = 1; n <= this.ctx.numPages; n++) {
      const el = document.createElement('div');
      el.className = 'ep-slot';
      el.dataset.page = String(n);
      this._sizeSlot(el);
      this.column.append(el);
      this.slots.push({ el, canvas: null, task: null, rendered: false });
    }

    this.observer = new IntersectionObserver(
      (entries) => entries.forEach((en) => {
        const n = Number(en.target.dataset.page);
        if (en.isIntersecting) void this._renderSlot(n);
        else this._release(this.slots[n - 1]);
      }),
      { root: this.ctx.stage, rootMargin: '800px 0px' },
    );
    this.slots.forEach((s) => this.observer.observe(s.el));
    this.ctx.stage.addEventListener('scroll', this._onScroll, { passive: true });

    this.goTo(this.ctx.page);
  }

  destroy() {
    if (this.observer) this.observer.disconnect();
    this.ctx.stage.removeEventListener('scroll', this._onScroll);
    this.slots.forEach((s) => this._release(s));
    this.slots = [];
    this.ctx.stage.classList.remove('ep-stage--continuous');
    this.ctx.stage.replaceChildren();
  }

  currentPage() {
    return this.ctx.page;
  }

  _fitScale() {
    if (this.ctx.fitMode === 'custom') return this.ctx.manualScale;
    return this.ctx.availWidth() / this.base.width; // width-based
  }

  _sizeSlot(el) {
    el.style.width = `${Math.floor(this.base.width * this.scale)}px`;
    el.style.height = `${Math.floor(this.base.height * this.scale)}px`;
  }

  goTo(n) {
    this.ctx.page = clamp(Math.round(n), 1, this.ctx.numPages);
    const slot = this.slots[this.ctx.page - 1];
    if (slot) slot.el.scrollIntoView({ block: 'start' });
    this.onPage(this.ctx.page);
  }

  relayout() {
    if (!this.base) return;
    this.scale = clamp(this._fitScale(), MIN_SCALE, MAX_SCALE);
    this.ctx.appliedScale = this.scale;
    const current = this.ctx.page;
    this.slots.forEach((s) => {
      this._release(s);
      this._sizeSlot(s.el);
    });
    this.goTo(current); // re-anchor; observer re-renders what's visible
  }

  _release(slot) {
    if (!slot) return;
    try { slot.task && slot.task.cancel(); } catch { /* ignore */ }
    slot.task = null;
    if (slot.canvas) {
      slot.canvas.remove();
      slot.canvas = null;
    }
    slot.rendered = false;
  }

  async _renderSlot(n) {
    const slot = this.slots[n - 1];
    if (!slot || slot.rendered) return;
    slot.rendered = true;
    try {
      const page = await this.ctx.pdfDoc.getPage(n);
      const canvas = document.createElement('canvas');
      canvas.className = 'ep-canvas';
      const { task } = renderPageInto(page, this.scale, canvas);
      slot.task = task;
      await task.promise;
      slot.task = null;
      slot.canvas = canvas;
      slot.el.replaceChildren(canvas);

      // تظليل تطابقات البحث في التمرير المتواصل أيضاً (الخانة position:relative).
      const highlighter = this.ctx.highlighter;
      if (highlighter && highlighter.active) await highlighter.render(page, this.scale, slot.el);
    } catch (e) {
      if (!isCancel(e)) slot.rendered = false; // allow retry
    }
  }

  _updateCurrent() {
    const top = this.ctx.stage.scrollTop;
    let best = this.ctx.page;
    let bestDelta = Infinity;
    for (const s of this.slots) {
      const delta = Math.abs(s.el.offsetTop - top);
      if (delta < bestDelta) {
        bestDelta = delta;
        best = Number(s.el.dataset.page);
      }
    }
    if (best !== this.ctx.page) {
      this.ctx.page = best;
      this.onPage(best);
    }
  }
}
