// Public advertising frontend — progressive enhancement entry point.
// Hydrates every server-rendered <div data-ad-zone="..."> slot. Vanilla ES module,
// no framework (mirrors broadcast.js/epaper.js). Loaded via @vite(['.../ads.js']).

import { initAdSlots } from './ads/slot.js';

function boot() {
    initAdSlots();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
