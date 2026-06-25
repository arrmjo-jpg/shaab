// Reader retention state (Phase 5): resume-reading + bookmarks.
// Authenticated → server persistence (web routes, cookie + X-CSRF-TOKEN).
// Guest → localStorage fallback (no server calls). Writes are best-effort
// (optimistic local set kept on network failure). Progress saves are debounced.

import { debounce } from './util.js';

export class ReaderState {
  constructor(opts) {
    this.issueKey = String(opts.issueKey || 'x');
    this.authenticated = !!opts.authenticated;
    this.csrf = opts.csrf || '';
    this.endpoints = opts.endpoints || {};
    this.bookmarksSet = new Set();
    this.lastPage = null;
    this._persistProgress = debounce((p) => void this._putProgress(p), 1500);
  }

  async load() {
    if (this.authenticated && this.endpoints.state) {
      try {
        const res = await fetch(this.endpoints.state, {
          headers: { Accept: 'application/json' },
          credentials: 'same-origin',
        });
        if (res.ok) {
          const data = await res.json();
          this.lastPage = Number.isFinite(data.last_page) ? Number(data.last_page) : null;
          this.bookmarksSet = new Set((Array.isArray(data.bookmarks) ? data.bookmarks : []).map(Number));

          return;
        }
      } catch { /* تعذّر الخادم — نرتدّ إلى المحلّي */ }
    }
    this._loadLocal();
  }

  resumePage() {
    return this.lastPage;
  }

  /** @returns {number[]} */
  bookmarks() {
    return [...this.bookmarksSet].sort((a, b) => a - b);
  }

  isBookmarked(page) {
    return this.bookmarksSet.has(page);
  }

  saveProgress(page) {
    if (!Number.isFinite(page) || page < 1) return;
    this.lastPage = page;
    if (this.authenticated && this.endpoints.progress) {
      this._persistProgress(page);
    } else {
      this._saveLocal();
    }
  }

  /** @returns {Promise<boolean>} الحالة الجديدة (true = صار مُؤشَّراً) */
  async toggleBookmark(page) {
    const had = this.bookmarksSet.has(page);
    if (had) this.bookmarksSet.delete(page);
    else this.bookmarksSet.add(page);

    if (this.authenticated && this.endpoints.bookmarks) {
      try {
        if (had) {
          await fetch(`${this.endpoints.bookmarks}/${page}`, {
            method: 'DELETE', headers: this._headers(), credentials: 'same-origin',
          });
        } else {
          await fetch(this.endpoints.bookmarks, {
            method: 'POST', headers: this._headers(true), credentials: 'same-origin',
            body: JSON.stringify({ page }),
          });
        }
      } catch { /* أبقِ التغيير المحلّي التفاؤليّ */ }
    } else {
      this._saveLocal();
    }

    return !had;
  }

  async _putProgress(page) {
    try {
      await fetch(this.endpoints.progress, {
        method: 'PUT', headers: this._headers(true), credentials: 'same-origin', keepalive: true,
        body: JSON.stringify({ page }),
      });
    } catch { /* أفضل جهد */ }
  }

  _headers(json = false) {
    const h = { Accept: 'application/json' };
    if (json) h['Content-Type'] = 'application/json';
    if (this.csrf) h['X-CSRF-TOKEN'] = this.csrf;

    return h;
  }

  _lsKey() {
    return `epaper:state:${this.issueKey}`;
  }

  _loadLocal() {
    try {
      const raw = localStorage.getItem(this._lsKey());
      if (!raw) return;
      const data = JSON.parse(raw);
      this.lastPage = Number.isFinite(data.lastPage) ? Number(data.lastPage) : null;
      this.bookmarksSet = new Set(Array.isArray(data.bookmarks) ? data.bookmarks.map(Number) : []);
    } catch { /* تجاهل */ }
  }

  _saveLocal() {
    try {
      localStorage.setItem(this._lsKey(), JSON.stringify({
        lastPage: this.lastPage,
        bookmarks: [...this.bookmarksSet],
      }));
    } catch { /* حصّة ممتلئة / وضع خاصّ — تجاهل */ }
  }
}
