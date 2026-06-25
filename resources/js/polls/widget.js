// Public poll widget hydration — vanilla ES module, progressive enhancement.
//
// A server-rendered <div data-poll-uuid="..."> shell is hydrated here: fetch the poll
// (server-computed per-actor state via a no-store call), render the vote form or the
// results (per result-visibility), submit votes through the rate-limited endpoint, and
// render results. Reuses the shared public API helper (X-Client-Id => guest voter identity).
// Resilient — failures never break the host page; the graceful SSR placeholder stays.

import { apiRequest, dataOf } from '../broadcast/api.js';

/** Parse the server-rendered i18n JSON embedded in the shell (translated per page locale). */
function labels(host) {
    const node = host.querySelector('[data-poll-i18n]');
    try {
        return node ? JSON.parse(node.textContent || '{}') : {};
    } catch {
        return {};
    }
}

/** Create an element with optional class + (textContent — XSS-safe for server text). */
function make(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text != null) node.textContent = text;
    return node;
}

function setState(host, state) {
    host.dataset.pollState = state;
}

/** Clear rendered children but keep the embedded i18n script. */
function clearBody(host) {
    Array.from(host.children).forEach((child) => {
        if (!child.matches('[data-poll-i18n]')) child.remove();
    });
}

function renderMessage(host, message) {
    clearBody(host);
    host.appendChild(make('p', 'poll-widget__message', message || ''));
}

function renderResults(host, poll, t, note) {
    clearBody(host);
    setState(host, 'results');

    host.appendChild(make('h3', 'poll-widget__question', poll.question));

    const results = poll.results || { total_votes: 0, options: [] };
    const byId = new Map(results.options.map((o) => [o.id, o]));
    const list = make('ul', 'poll-widget__results');

    (poll.options || []).forEach((option) => {
        const r = byId.get(option.id) || { votes_count: 0, percentage: 0 };
        const item = make('li', 'poll-widget__result');
        item.appendChild(make('span', 'poll-widget__result-label', option.label));
        item.appendChild(make('span', 'poll-widget__result-count', `${r.percentage}% (${r.votes_count})`));

        const bar = make('div', 'poll-widget__bar');
        const fill = make('div', 'poll-widget__bar-fill');
        fill.style.width = `${Math.max(0, Math.min(100, Number(r.percentage) || 0))}%`;
        bar.appendChild(fill);
        item.appendChild(bar);

        list.appendChild(item);
    });

    host.appendChild(list);
    host.appendChild(make('p', 'poll-widget__total', `${t.total_votes || ''}: ${results.total_votes}`));
    if (note) host.appendChild(make('p', 'poll-widget__note', note));
}

function renderForm(host, poll, t) {
    clearBody(host);
    setState(host, 'form');

    const form = make('form', 'poll-widget__form');
    form.appendChild(make('h3', 'poll-widget__question', poll.question));
    form.appendChild(make('p', 'poll-widget__hint', poll.allow_multiple ? t.choose_multiple : t.choose_one));

    const inputType = poll.allow_multiple ? 'checkbox' : 'radio';
    (poll.options || []).forEach((option) => {
        const label = make('label', 'poll-widget__option');
        const input = document.createElement('input');
        input.type = inputType;
        input.name = 'poll_option';
        input.value = String(option.id);
        label.appendChild(input);
        label.appendChild(make('span', 'poll-widget__option-label', option.label));
        form.appendChild(label);
    });

    const submit = make('button', 'poll-widget__submit', t.vote || 'Vote');
    submit.type = 'submit';
    form.appendChild(submit);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const ids = Array.from(form.querySelectorAll('input[name="poll_option"]:checked')).map((i) => Number(i.value));
        if (ids.length === 0) return;
        submit.disabled = true;
        void submitVote(host, poll, t, ids);
    });

    host.appendChild(form);
}

async function submitVote(host, poll, t, optionIds) {
    try {
        const res = await apiRequest('POST', `/polls/${encodeURIComponent(poll.uuid)}/vote`, {
            body: { option_ids: optionIds },
        });
        const data = dataOf(res);

        if (!res.ok) {
            // closed / invalid / not-authenticated — surface the server message.
            setState(host, 'error');
            renderMessage(host, (res.body && res.body.message) || t.error);
            return;
        }

        if (data && data.results) {
            poll.results = data.results;
            poll.has_voted = true;
            renderResults(host, poll, t, data.already_voted ? t.already_voted : t.thanks);
        } else {
            setState(host, 'voted');
            renderMessage(host, data && data.already_voted ? t.already_voted : t.thanks);
        }
    } catch {
        setState(host, 'error');
        renderMessage(host, t.error);
    }
}

function render(host, poll, t) {
    if (poll.is_open && ! poll.has_voted) {
        renderForm(host, poll, t);
        return;
    }

    if (poll.results) {
        renderResults(host, poll, t, poll.has_voted ? t.thanks : (poll.is_open ? '' : t.closed));
        return;
    }

    // Can't vote and no results visible to this actor.
    setState(host, poll.is_open ? 'voted' : 'closed');
    renderMessage(host, poll.is_open ? t.thanks : t.closed);
}

/** Hydrate a single [data-poll-uuid] widget. Idempotent; never throws to the host page. */
export async function hydratePollWidget(host) {
    if (!host || host.dataset.pollReady === '1') return;
    host.dataset.pollReady = '1';

    const uuid = host.dataset.pollUuid;
    if (!uuid) return;

    const t = labels(host);

    try {
        const res = await apiRequest('GET', `/polls/${encodeURIComponent(uuid)}`);
        const poll = dataOf(res);
        if (!res.ok || !poll) {
            setState(host, 'error');
            renderMessage(host, t.error);
            return;
        }
        render(host, poll, t);
    } catch {
        setState(host, 'error');
        renderMessage(host, t.error);
    }
}

/** Hydrate every poll widget under `root`. */
export function initPollWidgets(root = document) {
    root.querySelectorAll('[data-poll-uuid]').forEach((host) => void hydratePollWidget(host));
}
