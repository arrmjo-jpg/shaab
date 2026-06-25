// B5 presence — REAL HTTP heartbeat engine (no WebSockets, no fakery).
//
// Flow:
//   POST /broadcasts/{id}/presence/join      -> { token, state, status, viewers_now, heartbeat_interval }
//   POST /broadcasts/{id}/presence/heartbeat -> { state, status, viewers_now, heartbeat_interval }  (body: { token })
//
// Control-state (data.state): allowed -> keep; kicked/banned/closed -> stop+notice+teardown;
// ended -> ended UI; offline -> offline UI. HTTP 422 on heartbeat -> token expired -> rejoin.
// Network errors -> capped backoff. pagehide/visibilitychange(hidden) -> pause; visible -> resume.
// Server owns the heartbeat interval; we never hardcode it.

import { apiRequest, dataOf } from './api.js';

const DEFAULT_INTERVAL = 30; // seconds — only a seed; server value always wins
const MAX_BACKOFF_ATTEMPTS = 5;

const STATE_MESSAGES = {
    kicked: 'تم إنهاء جلستك في هذا البثّ. يمكنك إعادة الانضمام.',
    banned: 'تم حظرك من هذا البثّ.',
    closed: 'تم إغلاق جمهور هذا البثّ حالياً.',
    ended: 'انتهى هذا البثّ.',
    offline: 'البثّ خارج التغطية مؤقّتاً.',
};

export function initPresence(config, hooks = {}) {
    const { broadcastId, status } = config;
    // Presence only matters for LIVE and SCHEDULED broadcasts.
    if (!broadcastId || (status !== 'live' && status !== 'scheduled')) {
        return () => {};
    }

    const onViewers = typeof hooks.onViewers === 'function' ? hooks.onViewers : () => {};
    const onControl = typeof hooks.onControl === 'function' ? hooks.onControl : () => {};

    let token = null;
    let intervalSec = DEFAULT_INTERVAL;
    let timer = null;
    let backoff = 0;
    let stopped = false; // cooperative teardown (kicked/banned/closed/ended)
    let paused = false; // tab hidden

    const clearTimer = () => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    };

    const schedule = (seconds) => {
        clearTimer();
        if (stopped || paused) return;
        timer = window.setTimeout(loop, Math.max(1, seconds) * 1000);
    };

    const applyData = (data) => {
        if (!data) return;
        if (typeof data.heartbeat_interval === 'number' && data.heartbeat_interval > 0) {
            intervalSec = data.heartbeat_interval;
        }
        if (typeof data.viewers_now === 'number') {
            onViewers(data.viewers_now);
        }
        handleControlState(data.state);
    };

    const handleControlState = (state) => {
        switch (state) {
            case 'allowed':
                onControl({ state, message: null });
                return true;
            case 'kicked':
            case 'banned':
            case 'closed':
            case 'ended':
            case 'offline':
                stopped = true;
                clearTimer();
                onControl({ state, message: STATE_MESSAGES[state] ?? null });
                return false;
            default:
                return true;
        }
    };

    async function join() {
        const result = await apiRequest('POST', `/broadcasts/${broadcastId}/presence/join`);
        if (result.status === 404) {
            stopped = true;
            clearTimer();
            onControl({ state: 'unavailable', message: STATE_MESSAGES.offline });
            return false;
        }
        if (!result.ok) {
            return false; // transient — caller applies backoff
        }
        const data = dataOf(result);
        if (data && typeof data.token === 'string') {
            token = data.token;
        }
        applyData(data);
        backoff = 0;
        return !stopped;
    }

    async function heartbeat() {
        const result = await apiRequest('POST', `/broadcasts/${broadcastId}/presence/heartbeat`, {
            body: { token },
        });

        // 422 -> invalid/expired token -> rejoin to get a fresh one, then resume.
        if (result.status === 422) {
            token = null;
            const ok = await join();
            if (ok && !stopped) schedule(intervalSec);
            return;
        }

        if (result.status === 404) {
            stopped = true;
            clearTimer();
            onControl({ state: 'unavailable', message: STATE_MESSAGES.offline });
            return;
        }

        if (!result.ok) {
            throw new Error(`heartbeat failed: ${result.status}`); // -> backoff
        }

        applyData(dataOf(result));
        backoff = 0;
        if (!stopped) schedule(intervalSec);
    }

    async function loop() {
        if (stopped || paused) return;
        try {
            if (!token) {
                const ok = await join();
                if (!ok) {
                    if (stopped) return;
                    throw new Error('join failed');
                }
                if (!stopped) schedule(intervalSec);
                return;
            }
            await heartbeat();
        } catch {
            // Network/transient error: capped exponential backoff, no tight loop.
            backoff = Math.min(backoff + 1, MAX_BACKOFF_ATTEMPTS);
            const delay = intervalSec * Math.pow(2, backoff - 1);
            schedule(delay);
        }
    }

    // Tab lifecycle: stop pinging when hidden (no leave endpoint — server TTL reaps),
    // resume when visible. Cooperative teardown is permanent (stopped stays true).
    const onVisibility = () => {
        if (document.visibilityState === 'hidden') {
            paused = true;
            clearTimer();
        } else if (!stopped) {
            paused = false;
            schedule(0); // resume immediately
        }
    };

    const onPageHide = () => {
        paused = true;
        clearTimer();
    };

    document.addEventListener('visibilitychange', onVisibility);
    window.addEventListener('pagehide', onPageHide);

    // Kick off.
    loop();

    return () => {
        stopped = true;
        clearTimer();
        document.removeEventListener('visibilitychange', onVisibility);
        window.removeEventListener('pagehide', onPageHide);
    };
}
