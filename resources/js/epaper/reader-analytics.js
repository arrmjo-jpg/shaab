// Reader analytics (Phase 5) — practical, privacy-conscious, low-noise.
// Collects a single session summary (active duration, unique pages viewed with a
// dwell threshold, search terms, bookmark usage, resume usage) and flushes ONCE at
// session end via fetch(keepalive) with X-CSRF-TOKEN. No per-interaction pinging.

const DWELL_MS = 1200;       // صفحة تُحتسَب مشاهَدةً فقط بعد مكوث (تفادي ضجيج التقليب)
const MAX_PAGES = 1000;
const MAX_SEARCHES = 30;

export class ReaderAnalytics {
  constructor(opts) {
    this.endpoint = opts.endpoint || '';
    this.csrf = opts.csrf || '';
    this.activeMs = 0;
    this._visibleSince = document.visibilityState === 'visible' ? Date.now() : null;
    this.pages = new Set();
    this.searches = [];
    this.bookmarksUsed = 0;
    this.resumed = false;
    this._sent = false;
    this._pageTimer = null;
    this._onVisibility = () => this._handleVisibility();
    this._onHide = () => this.flush();
  }

  start() {
    if (!this.endpoint) return;
    document.addEventListener('visibilitychange', this._onVisibility);
    window.addEventListener('pagehide', this._onHide);
  }

  /** يحتسب الصفحة مشاهَدةً بعد عتبة مكوث (يُلغى عند تقليب سريع). */
  recordPage(page) {
    if (!this.endpoint || !(page >= 1)) return;
    if (this._pageTimer) clearTimeout(this._pageTimer);
    this._pageTimer = setTimeout(() => {
      if (this.pages.size < MAX_PAGES) this.pages.add(page);
    }, DWELL_MS);
  }

  recordSearch(term) {
    if (!this.endpoint) return;
    const t = (term || '').trim();
    if (t.length >= 2 && this.searches.length < MAX_SEARCHES) {
      this.searches.push(t.slice(0, 100));
    }
  }

  recordBookmark() {
    this.bookmarksUsed += 1;
  }

  recordResume() {
    this.resumed = true;
  }

  _handleVisibility() {
    if (document.visibilityState === 'hidden') {
      this._accrue();
      this.flush(); // أوثق إشارة على الجوّال
    } else {
      this._visibleSince = Date.now();
    }
  }

  _accrue() {
    if (this._visibleSince) {
      this.activeMs += Date.now() - this._visibleSince;
      this._visibleSince = null;
    }
  }

  /** يُرسَل مرّة واحدة لكل جلسة (حارس _sent) — بيكون نهاية الجلسة. */
  flush() {
    if (this._sent || !this.endpoint) return;
    this._sent = true;
    this._accrue();

    const payload = {
      duration: Math.round(this.activeMs / 1000),
      pages: [...this.pages],
      searches: this.searches,
      bookmarks_used: this.bookmarksUsed,
      resumed: this.resumed,
    };

    // بيكون نهاية الجلسة: navigator.sendBeacon أوثق عبر المتصفّحات (خاصّة iOS<16.4
    // حيث لا يُدعَم fetch keepalive) — نُمرّر CSRF في الجسم (_token) إذ لا يقبل البيكون
    // ترويسات مخصّصة، وLaravel يقرأ _token من الجسم. fetch(keepalive) بديلٌ احتياطيّ.
    try {
      if (typeof navigator !== 'undefined' && typeof navigator.sendBeacon === 'function') {
        const body = JSON.stringify({ ...payload, _token: this.csrf });
        if (navigator.sendBeacon(this.endpoint, new Blob([body], { type: 'application/json' }))) return;
      }
      void fetch(this.endpoint, {
        method: 'POST',
        keepalive: true,
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
          ...(this.csrf ? { 'X-CSRF-TOKEN': this.csrf } : {}),
        },
        body: JSON.stringify(payload),
      }).catch(() => {});
    } catch { /* أفضل جهد */ }
  }
}
