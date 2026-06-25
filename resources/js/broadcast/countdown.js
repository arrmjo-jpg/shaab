// Live-ticking countdown(s) to a target time. Reads the target from each
// element's `datetime` attribute (<time datetime="...">). RTL/Arabic-friendly:
// renders a compact HH:MM:SS (or D:HH:MM:SS) string. Stops at zero.

const ZERO = '00:00:00';

function pad(n) {
    return String(n).padStart(2, '0');
}

function format(msRemaining) {
    if (msRemaining <= 0) return ZERO;
    const totalSec = Math.floor(msRemaining / 1000);
    const days = Math.floor(totalSec / 86400);
    const hours = Math.floor((totalSec % 86400) / 3600);
    const minutes = Math.floor((totalSec % 3600) / 60);
    const seconds = totalSec % 60;
    const base = `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
    return days > 0 ? `${days}:${base}` : base;
}

/**
 * Start ticking every countdown node under `root`. Returns a teardown function.
 * When a countdown reaches zero, it fires the optional onElapsed(node) callback
 * once (used on the detail page to nudge a reload into the live state).
 */
export function initCountdowns(root = document, onElapsed = null) {
    const nodes = Array.from(root.querySelectorAll('[data-countdown]'));
    if (nodes.length === 0) return () => {};

    const fired = new WeakSet();

    const targets = nodes
        .map((node) => {
            const iso = node.getAttribute('datetime');
            const at = iso ? Date.parse(iso) : NaN;
            return Number.isNaN(at) ? null : { node, at };
        })
        .filter(Boolean);

    if (targets.length === 0) return () => {};

    let timer = null;

    const tick = () => {
        const now = Date.now();
        let allElapsed = true;
        for (const { node, at } of targets) {
            const remaining = at - now;
            node.textContent = format(remaining);
            if (remaining > 0) {
                allElapsed = false;
            } else if (!fired.has(node)) {
                fired.add(node);
                if (typeof onElapsed === 'function') onElapsed(node);
            }
        }
        if (allElapsed && timer) {
            clearInterval(timer);
            timer = null;
        }
    };

    tick();
    timer = window.setInterval(tick, 1000);

    return () => {
        if (timer) clearInterval(timer);
        timer = null;
    };
}
