// Cross-issue archive search (Phase 6) — progressive enhancement on the epaper
// index page. Talks to the DB-backed archive endpoint (epaper.search.archive).
// RTL-first + Arabic-safe (logical CSS, dir=auto inputs, textContent rendering,
// no innerHTML). Debounced input; in-flight requests aborted on new input; a tag
// guard drops stale responses. Results are <a> deep links (issue + first matching
// page + ?q) so navigation reuses the normal reader route — the reader highlights
// on arrival. While a query is active the SSR issues grid is hidden; clearing it
// restores the grid (so no-JS and cleared-search both show the full archive).

import { debounce } from './util.js';

const MIN_QUERY = 2;
const DEBOUNCE_MS = 350;

export class ArchiveSearch {
  constructor(root, labels) {
    this.root = root;
    this.t = labels || {};
    this.endpoint = root.getAttribute('data-endpoint') || '';
    this.grid = document.querySelector('[data-epaper-grid]');
    this.controller = null;
    this.tag = ''; // أحدث طلب فعّال — تُتجاهَل الاستجابات القديمة
    this.page = 1;
    this.totalPages = 1;
    this.filters = { issue_number: '', date_from: '', date_to: '' };
    this._debounced = debounce(() => void this._runFresh(), DEBOUNCE_MS);
  }

  init() {
    if (!this.endpoint) return;
    this._build();
  }

  _t(key, fallback) {
    if (this.t[key] != null) return this.t[key];

    return fallback != null ? fallback : key;
  }

  _build() {
    const form = document.createElement('form');
    form.className = 'ep-arc-form';
    form.setAttribute('role', 'search');

    this.input = document.createElement('input');
    this.input.type = 'search';
    this.input.className = 'ep-arc-input';
    this.input.autocomplete = 'off';
    this.input.dir = 'auto'; // عربيّ-آمن: الاتجاه يتبع المُدخَل
    this.input.placeholder = this._t('placeholder', '');
    this.input.setAttribute('aria-label', this._t('label', 'Search'));

    this.filterToggle = document.createElement('button');
    this.filterToggle.type = 'button';
    this.filterToggle.className = 'ep-arc-filtertoggle';
    this.filterToggle.textContent = this._t('filters', 'Filters');
    this.filterToggle.setAttribute('aria-expanded', 'false');

    form.append(this.input, this.filterToggle);

    // لوحة المرشّحات: رقم العدد + مدى التاريخ.
    this.filterPanel = document.createElement('div');
    this.filterPanel.className = 'ep-arc-filters';
    this.filterPanel.hidden = true;

    this.issueField = this._field('issue_number', 'number');
    this.fromField = this._field('date_from', 'date');
    this.toField = this._field('date_to', 'date');

    const clearBtn = document.createElement('button');
    clearBtn.type = 'button';
    clearBtn.className = 'ep-arc-clear';
    clearBtn.textContent = this._t('clear', 'Clear');

    this.filterPanel.append(this.issueField.wrap, this.fromField.wrap, this.toField.wrap, clearBtn);

    this.status = document.createElement('div');
    this.status.className = 'ep-arc-status';
    this.status.setAttribute('aria-live', 'polite');

    this.results = document.createElement('div');
    this.results.className = 'ep-arc-results';

    this.moreBtn = document.createElement('button');
    this.moreBtn.type = 'button';
    this.moreBtn.className = 'ep-arc-more';
    this.moreBtn.textContent = this._t('more', 'Load more');
    this.moreBtn.hidden = true;

    this.root.append(form, this.filterPanel, this.status, this.results, this.moreBtn);

    form.addEventListener('submit', (e) => {
      e.preventDefault();
      if (this.input.value.trim().length >= MIN_QUERY) void this._runFresh();
    });
    this.input.addEventListener('input', () => {
      if (this.input.value.trim().length < MIN_QUERY) {
        this.abort();
        this._reset();

        return;
      }
      this._debounced();
    });
    this.filterToggle.addEventListener('click', () => this._toggleFilters());
    for (const f of [this.issueField, this.fromField, this.toField]) {
      f.input.addEventListener('change', () => {
        if (this.input.value.trim().length >= MIN_QUERY) void this._runFresh();
      });
    }
    clearBtn.addEventListener('click', () => this._clearFilters());
    this.moreBtn.addEventListener('click', () => void this._loadMore());
  }

  _field(key, type) {
    const wrap = document.createElement('label');
    wrap.className = 'ep-arc-field';

    const span = document.createElement('span');
    span.className = 'ep-arc-fieldlabel';
    span.textContent = this._t(key, key);

    const input = document.createElement('input');
    input.type = type;
    input.className = 'ep-arc-fieldinput';
    if (type === 'number') {
      input.min = '1';
      input.inputMode = 'numeric';
    }

    wrap.append(span, input);

    return { wrap, input };
  }

  _toggleFilters(force) {
    const open = force != null ? force : this.filterPanel.hidden;
    this.filterPanel.hidden = !open;
    this.filterToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
  }

  _clearFilters() {
    this.issueField.input.value = '';
    this.fromField.input.value = '';
    this.toField.input.value = '';
    if (this.input.value.trim().length >= MIN_QUERY) void this._runFresh();
  }

  abort() {
    if (this.controller) {
      this.controller.abort();
      this.controller = null;
    }
  }

  _reset() {
    this.results.replaceChildren();
    this.moreBtn.hidden = true;
    this.status.textContent = '';
    this._showGrid(true);
  }

  _showGrid(show) {
    if (this.grid) this.grid.hidden = !show;
  }

  async _runFresh() {
    this.page = 1;
    await this._fetch(false);
  }

  async _loadMore() {
    this.page += 1;
    await this._fetch(true);
  }

  async _fetch(append) {
    const q = this.input.value.trim();
    if (q.length < MIN_QUERY) {
      this._reset();

      return;
    }

    this.filters = {
      issue_number: this.issueField.input.value.trim(),
      date_from: this.fromField.input.value,
      date_to: this.toField.input.value,
    };
    const tag = `${q}|${this.page}|${JSON.stringify(this.filters)}`;
    this.tag = tag;

    this.abort();
    this.controller = new AbortController();
    this._showGrid(false);
    if (!append) this.results.replaceChildren();
    this.status.textContent = this._t('loading', '');
    this.moreBtn.hidden = true;

    try {
      const url = new URL(this.endpoint, window.location.origin);
      url.searchParams.set('q', q);
      url.searchParams.set('page', String(this.page));
      if (this.filters.issue_number) url.searchParams.set('issue_number', this.filters.issue_number);
      if (this.filters.date_from) url.searchParams.set('date_from', this.filters.date_from);
      if (this.filters.date_to) url.searchParams.set('date_to', this.filters.date_to);

      const res = await fetch(url.toString(), {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
        signal: this.controller.signal,
      });
      if (tag !== this.tag) return; // طلب أحدث سبقه — تجاهل
      if (!res.ok) {
        this._error();

        return;
      }
      const json = await res.json();
      if (tag !== this.tag) return;
      this._render(q, (json && json.data) || {}, (json && json.meta) || {}, append);
    } catch (e) {
      if (e && e.name === 'AbortError') return;
      this._error();
    } finally {
      this.controller = null;
    }
  }

  _render(query, data, meta, append) {
    const results = Array.isArray(data.results) ? data.results : [];
    const total = typeof data.total === 'number' ? data.total : results.length;
    const pagination = meta.pagination || {};
    this.totalPages = pagination.total_pages || 1;

    if (!append) this.results.replaceChildren();

    if (total === 0 && !append) {
      this.status.textContent = this._t('empty', '');
      this.moreBtn.hidden = true;

      return;
    }

    this.status.textContent = this._t('count', ':count').replace(':count', String(total));
    for (const r of results) this.results.append(this._resultEl(query, r));
    this.moreBtn.hidden = this.page >= this.totalPages;
  }

  _resultEl(query, r) {
    const a = document.createElement('a');
    a.className = 'ep-arc-result';
    a.href = String(r.url || r.path || '#');

    const meta = document.createElement('div');
    meta.className = 'ep-arc-result-meta';
    const num = document.createElement('span');
    num.className = 'ep-arc-result-num';
    num.textContent = `#${r.issue_number != null ? r.issue_number : ''}`;
    meta.append(num);
    if (r.publication_date) {
      const date = document.createElement('span');
      date.className = 'ep-arc-result-date';
      date.textContent = String(r.publication_date);
      meta.append(date);
    }

    const title = document.createElement('h3');
    title.className = 'ep-arc-result-title';
    title.dir = 'auto';
    title.textContent = String(r.title || '');

    const snip = document.createElement('p');
    snip.className = 'ep-arc-result-snippet';
    snip.dir = 'auto';
    const m = r.match || {};
    this._highlight(snip, String(m.snippet || ''), query);

    const sub = document.createElement('div');
    sub.className = 'ep-arc-result-sub';
    const pageEl = document.createElement('span');
    pageEl.textContent = this._t('result_page', 'Page :page').replace(':page', String(m.page != null ? m.page : 1));
    sub.append(pageEl);
    if (typeof m.pages_matched === 'number' && m.pages_matched > 1) {
      const pm = document.createElement('span');
      pm.textContent = this._t('pages_matched', ':count').replace(':count', String(m.pages_matched));
      sub.append(pm);
    }

    a.append(meta, title, snip, sub);

    return a;
  }

  /** تظليل آمن (عُقَد نصّية + <mark>) — لا innerHTML، فلا حقن. */
  _highlight(el, text, query) {
    el.replaceChildren();
    const q = (query || '').toLowerCase();
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

  _error() {
    this.status.textContent = this._t('error', '');
    this.moreBtn.hidden = true;
  }
}
