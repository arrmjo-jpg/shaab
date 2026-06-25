// Public ad-slot hydration — vanilla ES module, progressive enhancement.
//
// A server-rendered <div data-ad-zone="home_top"> is hydrated here: fetch the
// server-selected creative, render it (image or sanitized HTML), fire a
// visibility-gated impression beacon exactly once, and route clicks through the
// signed redirect. Reuses the shared public API helper (same X-Client-Id, so ad
// dedup/rate-limiting aligns with engagement). The serve endpoint is edge-cached
// per time-bucket — we never cache-bust; the impression beacon is no-store.

import { apiRequest, dataOf } from '../broadcast/api.js';

/** Coarse device class for serving segmentation (mirrors AdDeviceClass). */
function detectDevice() {
    const w = window.innerWidth || document.documentElement.clientWidth || 1280;
    if (w < 768) return 'mobile';
    if (w < 1024) return 'tablet';
    return 'desktop';
}

/** Page locale (ar default) — overridable per slot via data-locale. */
function pageLocale() {
    const lang = (document.documentElement.lang || '').slice(0, 2).toLowerCase();
    return lang === 'en' ? 'en' : 'ar';
}

/**
 * Render the creative into the slot.
 *  - image → a click-wrapped <img> (whole-creative click via the signed redirect).
 *  - html  → sanitized markup as-is (purified server-side by HTMLPurifier; the
 *            advertiser markup owns its own links).
 * Returns true if something was rendered.
 */
function renderCreative(el, ad) {
    el.textContent = '';

    if (ad.type === 'image' && ad.render && ad.render.image_url) {
        const img = document.createElement('img');
        img.src = ad.render.image_url;
        img.alt = ad.render.alt || '';
        img.loading = 'lazy';
        img.decoding = 'async';
        if (ad.width) img.width = ad.width;
        if (ad.height) img.height = ad.height;
        img.style.display = 'block';
        img.style.maxWidth = '100%';

        if (ad.click && ad.click.url) {
            const a = document.createElement('a');
            a.href = ad.click.url;
            a.target = '_blank';
            a.rel = 'noopener noreferrer sponsored';
            a.appendChild(img);
            el.appendChild(a);
        } else {
            el.appendChild(img);
        }
        return true;
    }

    if (ad.type === 'html' && ad.render && typeof ad.render.html === 'string') {
        // Source is sanitized server-side (no scripts/iframes/handlers) — safe to inject.
        el.innerHTML = ad.render.html;
        return true;
    }

    return false; // unknown/unsupported (e.g. video) — leave the slot empty
}

let observer = null;

function ensureObserver() {
    if (observer || !('IntersectionObserver' in window)) return observer;
    observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (!entry.isIntersecting) continue;
                const el = entry.target;
                observer.unobserve(el); // fire once per slot
                fireImpression(el.dataset.adToken);
                delete el.dataset.adToken;
            }
        },
        { threshold: 0.5 },
    );
    return observer;
}

/** Confirm the impression once the slot is actually visible. Resilient — errors ignored. */
function fireImpression(token) {
    if (!token) return;
    apiRequest('POST', '/ads/track/impression', { body: { token } }).catch(() => {});
}

/** Count a click on an HTML-creative link (no redirect — advertiser markup owns its href). */
function fireClick(token) {
    if (!token) return;
    apiRequest('POST', '/ads/track/click', { body: { token } }).catch(() => {});
}

/**
 * HTML creatives carry their own <a> links (sanitized server-side); they don't route through
 * the signed redirect like image creatives do. Delegate clicks on those links to fire the click
 * beacon once (keepalive POST survives navigation), then let navigation proceed unchanged.
 * Idempotent — wires a single delegated listener per slot.
 */
function wireHtmlClicks(el, token) {
    if (!token || el.dataset.adClickWired === '1') return;
    el.dataset.adClickWired = '1';
    el.addEventListener('click', (event) => {
        const target = event.target;
        const anchor = target && target.closest ? target.closest('a[href]') : null;
        if (anchor && el.contains(anchor)) fireClick(token);
    });
}

function observeImpression(el, token) {
    if (!token) return;
    const io = ensureObserver();
    if (!io) {
        fireImpression(token); // no IntersectionObserver — count on render (degraded)
        return;
    }
    el.dataset.adToken = token;
    io.observe(el);
}

/** Hydrate a single [data-ad-zone] slot. Idempotent (guards against double-hydration). */
export async function hydrateSlot(el) {
    if (!el || el.dataset.adReady === '1') return;
    el.dataset.adReady = '1';

    const zone = el.dataset.adZone;
    if (!zone) return;

    const locale = el.dataset.locale || pageLocale();
    const device = el.dataset.device || detectDevice();
    const query = new URLSearchParams({ locale, device }).toString();

    try {
        const res = await apiRequest('GET', `/ads/serve/${encodeURIComponent(zone)}?${query}`);
        const ad = dataOf(res)?.ad ?? null;

        if (!ad || !renderCreative(el, ad)) {
            el.dataset.adState = 'empty'; // graceful empty state — slot stays collapsed
            return;
        }

        el.dataset.adState = 'filled';

        const token = ad.impression && ad.impression.token;
        // إبداعات HTML: روابطها الخاصّة ⇒ احتساب النقرة بمنارة (V2). الصورة: تحويل موقّع.
        if (ad.type === 'html') wireHtmlClicks(el, token);
        observeImpression(el, token);
    } catch {
        el.dataset.adState = 'error'; // resilient — never break the host page
    }
}

/** Hydrate every ad slot under `root`. */
export function initAdSlots(root = document) {
    root.querySelectorAll('[data-ad-zone]').forEach((el) => void hydrateSlot(el));
}
