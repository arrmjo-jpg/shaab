// Public polls frontend — progressive enhancement entry point.
// Hydrates every server-rendered <div data-poll-uuid="..."> widget. Vanilla ES module,
// no framework (mirrors ads.js/broadcast.js). Loaded via @vite(['.../polls.js']).

import { initPollWidgets } from './polls/widget.js';

function boot() {
    initPollWidgets();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
