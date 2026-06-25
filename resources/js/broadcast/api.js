// Lightweight same-origin API helpers for the public broadcast surface.
// No framework, no axios — just fetch. Base API URL is same-origin /api/v1.

const BASE = '/api/v1';
const TOKEN_KEY = 'alphacms.token';
const CLIENT_ID_KEY = 'alphacms.cid';

/** Bearer token provisioned by the platform's existing public auth (out of B10 scope). */
export function authToken() {
    try {
        return window.localStorage.getItem(TOKEN_KEY);
    } catch {
        return null; // storage may be blocked (private mode) — degrade gracefully
    }
}

export function hasAuth() {
    const t = authToken();
    return typeof t === 'string' && t.length > 0;
}

/**
 * Stable per-browser client id. Sent as X-Client-Id so the presence/engagement rate
 * limiters key per-browser, NOT per-IP — otherwise thousands of guests behind a shared
 * carrier/NAT IP collide on one 20/min bucket and get falsely throttled at scale.
 */
function clientId() {
    try {
        let id = window.localStorage.getItem(CLIENT_ID_KEY);
        if (!id) {
            id =
                window.crypto && typeof window.crypto.randomUUID === 'function'
                    ? window.crypto.randomUUID()
                    : `c-${Date.now().toString(36)}-${Math.random().toString(36).slice(2, 12)}`;
            window.localStorage.setItem(CLIENT_ID_KEY, id);
        }
        return id;
    } catch {
        return null; // private mode — server falls back to IP (degraded but functional)
    }
}

function headers(withAuth) {
    const h = { Accept: 'application/json', 'Content-Type': 'application/json' };
    const cid = clientId();
    if (cid) h['X-Client-Id'] = cid;
    if (withAuth) {
        const t = authToken();
        if (t) h.Authorization = `Bearer ${t}`;
    }
    return h;
}

/**
 * Perform an API request. Resolves to { ok, status, body } and never throws on
 * HTTP error codes (callers branch on status: 403 / 404 / 422 are meaningful).
 * Network failures reject so callers can apply backoff.
 */
export async function apiRequest(method, path, { body = null, withAuth = false } = {}) {
    const res = await fetch(`${BASE}${path}`, {
        method,
        headers: headers(withAuth),
        body: body !== null ? JSON.stringify(body) : undefined,
        credentials: 'same-origin',
        keepalive: method === 'POST', // best-effort delivery on teardown
    });

    let parsed = null;
    try {
        parsed = await res.json();
    } catch {
        parsed = null;
    }

    return { ok: res.ok, status: res.status, body: parsed };
}

/** Convenience: the `data` envelope key, or null. */
export function dataOf(result) {
    return result && result.body && typeof result.body === 'object' ? (result.body.data ?? null) : null;
}
