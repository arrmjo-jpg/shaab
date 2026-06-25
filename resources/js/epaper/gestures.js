// Touch gestures for the reader: horizontal swipe (page turn, RTL-aware),
// pinch-zoom (live), and double-tap zoom. Single-finger vertical scrolling is left
// to the browser (we only preventDefault during a pinch).

const SWIPE_MIN = 50; // px horizontal travel
const SWIPE_DOMINANCE = 1.4; // horizontal must dominate vertical
const SWIPE_MAX_MS = 600;
const DOUBLE_TAP_MS = 300;
const DOUBLE_TAP_DIST = 30;

function distance(touches) {
  return Math.hypot(touches[0].clientX - touches[1].clientX, touches[0].clientY - touches[1].clientY);
}

function midpoint(touches) {
  return { x: (touches[0].clientX + touches[1].clientX) / 2, y: (touches[0].clientY + touches[1].clientY) / 2 };
}

/**
 * @param {HTMLElement} el
 * @param {{onNext?:fn,onPrev?:fn,onPinchStart?:fn,onPinchMove?:fn,onPinchEnd?:fn,onPinchCancel?:fn,onDoubleTap?:fn}} h
 * @returns {() => void} detach
 */
export function attachGestures(el, h) {
  let startX = 0;
  let startY = 0;
  let startT = 0;
  let moved = false;
  let pinching = false;
  let startDist = 0;
  let lastTapT = 0;
  let lastTapX = 0;
  let lastTapY = 0;

  function onStart(e) {
    if (e.touches.length === 2) {
      pinching = true;
      moved = true;
      startDist = distance(e.touches) || 1;
      h.onPinchStart && h.onPinchStart(midpoint(e.touches));
      e.preventDefault();
    } else if (e.touches.length === 1) {
      const t = e.touches[0];
      startX = t.clientX;
      startY = t.clientY;
      startT = Date.now();
      moved = false;
    }
  }

  function onMove(e) {
    if (pinching && e.touches.length === 2) {
      h.onPinchMove && h.onPinchMove(distance(e.touches) / startDist, midpoint(e.touches));
      e.preventDefault();
      return;
    }
    if (e.touches.length === 1) {
      const t = e.touches[0];
      if (Math.abs(t.clientX - startX) > 10 || Math.abs(t.clientY - startY) > 10) moved = true;
    }
  }

  function onEnd(e) {
    if (pinching) {
      if (e.touches.length === 0) {
        pinching = false;
        h.onPinchEnd && h.onPinchEnd();
      }
      return;
    }

    const c = e.changedTouches[0];
    if (!c) return;
    const dx = c.clientX - startX;
    const dy = c.clientY - startY;
    const dt = Date.now() - startT;

    if (Math.abs(dx) > SWIPE_MIN && Math.abs(dx) > Math.abs(dy) * SWIPE_DOMINANCE && dt < SWIPE_MAX_MS) {
      const rtl = document.documentElement.dir === 'rtl';
      const leftward = dx < 0; // finger moved right→left
      // Leftward swipe advances reading in LTR, retreats in RTL.
      if (leftward) (rtl ? h.onPrev : h.onNext) && (rtl ? h.onPrev() : h.onNext());
      else (rtl ? h.onNext : h.onPrev) && (rtl ? h.onNext() : h.onPrev());
      return;
    }

    if (!moved) {
      const now = Date.now();
      if (now - lastTapT < DOUBLE_TAP_MS
        && Math.hypot(c.clientX - lastTapX, c.clientY - lastTapY) < DOUBLE_TAP_DIST) {
        e.preventDefault(); // اكبح تكبير-iOS المزدوج الأصليّ كي لا يتعارض مع تكبيرنا
        h.onDoubleTap && h.onDoubleTap({ x: c.clientX, y: c.clientY });
        lastTapT = 0;
      } else {
        lastTapT = now;
        lastTapX = c.clientX;
        lastTapY = c.clientY;
      }
    }
  }

  function onCancel() {
    if (pinching) {
      pinching = false;
      h.onPinchCancel && h.onPinchCancel(); // نظّف تحويل التكبير العالق على iOS
    }
  }

  el.addEventListener('touchstart', onStart, { passive: false });
  el.addEventListener('touchmove', onMove, { passive: false });
  el.addEventListener('touchend', onEnd, { passive: false }); // preventDefault على النقر المزدوج
  el.addEventListener('touchcancel', onCancel);

  return () => {
    el.removeEventListener('touchstart', onStart);
    el.removeEventListener('touchmove', onMove);
    el.removeEventListener('touchend', onEnd);
    el.removeEventListener('touchcancel', onCancel);
  };
}
