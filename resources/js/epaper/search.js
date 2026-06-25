// In-issue search panel for the epaper reader (Phase 4c). Talks to the Phase 4b
// DB-backed endpoint (current issue only). RTL-first + Arabic-safe (logical CSS,
// textContent rendering). Debounced input to avoid throttle abuse; in-flight
// requests are aborted on new input / close. Result click → reader.goTo(page).

import { debounce } from './util.js';

const MIN_QUERY = 2;
const DEBOUNCE_MS = 350;

export class EpaperSearch {
  constructor(panel, opts) {
    this.panel = panel;
    this.endpoint = opts.endpoint;
    this.t = opts.labels || {};
    this.searchable = opts.searchable !== false;
    this.onJump = opts.onJump || (() => {});
    this.onClose = opts.onClose || (() => {});
    this.onQuery = opts.onQuery || (() => {});
    this.controller = null;
    this.lastQuery = '';
    this._build();
    this._debounced = debounce((q) => void this._search(q), DEBOUNCE_MS);
  }

  _build() {
    this.panel.replaceChildren();

    const form = document.createElement('form');
    form.className = 'ep-search-form';

    this.input = document.createElement('input');
    this.input.type = 'search';
    this.input.className = 'ep-search-input';
    this.input.autocomplete = 'off';
    this.input.dir = 'auto'; // عربيّ-آمن: الاتجاه يتبع المُدخَل
    this.input.placeholder = this.t.searchPlaceholder || this.t.search || '';
    this.input.setAttribute('aria-label', this.t.search || 'Search');
    form.append(this.input);

    this.status = document.createElement('div');
    this.status.className = 'ep-search-status';
    this.status.setAttribute('aria-live', 'polite');

    this.results = document.createElement('div');
    this.results.className = 'ep-search-results';

    this.panel.append(form, this.status, this.results);

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      void this._search(this.input.value);
    });
    this.input.addEventListener('input', () => {
      const q = this.input.value.trim();
      if (q.length < MIN_QUERY) {
        this.abort();
        this.results.replaceChildren();
        this._hint();

        return;
      }
      this._debounced(q);
    });
    this.input.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        e.stopPropagation();
        this.onClose();
      }
    });

    if (!this.searchable) {
      this._unavailable();
    } else {
      this._hint();
    }
  }

  focus() {
    if (this.searchable && this.input) this.input.focus();
  }

  abort() {
    if (this.controller) {
      this.controller.abort();
      this.controller = null;
    }
  }

  async _search(raw) {
    const q = (raw || '').trim();
    if (!this.searchable) {
      this._unavailable();

      return;
    }
    if (q.length < MIN_QUERY) {
      this._hint();

      return;
    }

    this.lastQuery = q;
    this.abort();
    this.controller = new AbortController();
    this._loading();

    try {
      const url = `${this.endpoint}?q=${encodeURIComponent(q)}`;
      const res = await fetch(url, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: this.controller.signal,
      });
      if (!res.ok) throw new Error(`search ${res.status}`);
      const json = await res.json();
      if (q !== this.lastQuery) return; // نتيجة قديمة — تجاهل
      this.onQuery(q); // تتبّع تحليليّ: عبارة بحث اكتملت فعلاً (لا ضجيج كتابة جزئيّة)
      this._renderResults(q, (json && json.data) || {});
    } catch (e) {
      if (e && e.name === 'AbortError') return;
      this._error();
    } finally {
      this.controller = null;
    }
  }

  /** @param {{searchable?:boolean,total?:number,results?:Array<{page:number,snippet:string,matches:number}>}} data */
  _renderResults(query, data) {
    if (data.searchable === false) {
      this._unavailable();

      return;
    }

    const results = Array.isArray(data.results) ? data.results : [];
    this.results.replaceChildren();

    if (results.length === 0) {
      this.status.textContent = this.t.searchEmpty || '';

      return;
    }

    const total = typeof data.total === 'number' ? data.total : results.length;
    this.status.textContent = (this.t.searchCount || '').replace(':count', String(total));

    for (const r of results) {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'ep-search-result';

      const pageEl = document.createElement('span');
      pageEl.className = 'ep-search-page';
      pageEl.textContent = (this.t.searchPageLabel || 'Page :page').replace(':page', String(r.page));

      const snip = document.createElement('span');
      snip.className = 'ep-search-snippet';
      snip.dir = 'auto';
      this._highlight(snip, String(r.snippet || ''), query);

      btn.append(pageEl, snip);
      btn.addEventListener('click', () => this.onJump(r.page));
      this.results.append(btn);
    }
  }

  /** تظليل آمن (عُقَد نصّية + <mark>) — لا innerHTML، فلا حقن. */
  _highlight(el, text, query) {
    el.replaceChildren();
    const q = query.toLowerCase();
    if (q === '') {
      el.textContent = text;

      return;
    }
    const hay = text.toLowerCase();
    let from = 0;
    let idx = hay.indexOf(q, from);
    if (idx === -1) {
      el.textContent = text;

      return;
    }
    while (idx !== -1) {
      if (idx > from) el.append(document.createTextNode(text.slice(from, idx)));
      const mark = document.createElement('mark');
      mark.textContent = text.slice(idx, idx + query.length);
      el.append(mark);
      from = idx + query.length;
      idx = hay.indexOf(q, from);
    }
    if (from < text.length) el.append(document.createTextNode(text.slice(from)));
  }

  _loading() {
    this.results.replaceChildren();
    this.status.textContent = this.t.searchLoading || '';
  }

  _hint() {
    this.status.textContent = this.t.searchHint || '';
  }

  _error() {
    this.results.replaceChildren();
    this.status.textContent = this.t.searchError || '';
  }

  _unavailable() {
    if (this.input) {
      this.input.disabled = true;
      this.input.placeholder = this.t.searchUnavailable || '';
    }
    this.results.replaceChildren();
    this.status.textContent = this.t.searchUnavailable || '';
  }
}
