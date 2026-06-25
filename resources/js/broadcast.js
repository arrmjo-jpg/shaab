// Public Broadcast frontend — progressive enhancement entry point.
// Vanilla ES modules, no framework. SSR renders everything crawlers need;
// this layer hydrates the live bits (countdown, presence, reactions, reminders,
// player) from data-attributes. Loaded via @vite(['.../broadcast.js']).

import { initCountdowns } from './broadcast/countdown.js';
import { initPresence } from './broadcast/presence.js';
import { initReactions } from './broadcast/reactions.js';
import { initReminders } from './broadcast/reminders.js';
import { initPlayer } from './broadcast/player.js';

function readConfig() {
    const root = document.querySelector('[data-broadcast-id]');
    if (!root) return null;
    return {
        root,
        broadcastId: root.getAttribute('data-broadcast-id'),
        status: root.getAttribute('data-status'),
        kind: root.getAttribute('data-kind'),
        sourceType: root.getAttribute('data-source-type'),
        sourceUrl: root.getAttribute('data-source-url') || null,
    };
}

/** Update every viewers-now count node (listing cards seed [data-broadcast-id]). */
function renderViewers(count) {
    const text = new Intl.NumberFormat('ar').format(count);
    document.querySelectorAll('[data-viewers-count]').forEach((node) => {
        node.textContent = text;
    });
}

/** React to presence control-state: tear down the player + show a notice. */
function handleControl({ state, message }) {
    if (state === 'allowed' || !message) return;

    // Stop any media playback (cooperative — the source is external, best-effort).
    document.querySelectorAll('[data-player] video, [data-player] audio').forEach((el) => {
        try {
            el.pause();
            el.removeAttribute('src');
            el.load();
        } catch {
            /* ignore */
        }
    });
    const mount = document.querySelector('[data-player]');
    if (mount) {
        mount.querySelectorAll('iframe').forEach((f) => f.remove());
        const placeholder = mount.querySelector('[data-player-placeholder]');
        if (placeholder) placeholder.remove();
    }

    const notice = document.querySelector('[data-presence-notice]');
    if (notice) {
        notice.textContent = message;
        notice.classList.remove('hidden');
        notice.classList.add('flex');
    }
}

function boot() {
    // Countdowns exist on both listings and detail. On detail, reaching zero for
    // a scheduled broadcast reloads into the (now) live state.
    const detail = readConfig();
    initCountdowns(document, () => {
        if (detail && detail.status === 'scheduled') {
            window.setTimeout(() => window.location.reload(), 1500);
        }
    });

    if (!detail) return; // listing page — nothing more to hydrate

    // Player first (so presence teardown can stop it if control-state flips).
    initPlayer(detail);

    initPresence(detail, {
        onViewers: renderViewers,
        onControl: handleControl,
    });

    initReactions(detail);
    initReminders(detail);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot, { once: true });
} else {
    boot();
}
