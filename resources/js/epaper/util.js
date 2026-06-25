// Shared helpers for the epaper reader.

const DPR_CAP = 2; // bound canvas backing-store on hi-dpi to protect memory

export function clamp(v, min, max) {
  return Math.min(Math.max(v, min), max);
}

export function debounce(fn, ms) {
  let h;
  return (...args) => {
    clearTimeout(h);
    h = setTimeout(() => fn(...args), ms);
  };
}

export function throttle(fn, ms) {
  let last = 0;
  let timer = null;
  return (...args) => {
    const now = Date.now();
    const remaining = ms - (now - last);
    if (remaining <= 0) {
      last = now;
      fn(...args);
    } else if (timer === null) {
      timer = setTimeout(() => {
        last = Date.now();
        timer = null;
        fn(...args);
      }, remaining);
    }
  };
}

export function dprScale() {
  return Math.min(window.devicePixelRatio || 1, DPR_CAP);
}

export function isNarrow() {
  return window.matchMedia('(max-width: 640px)').matches;
}

/**
 * Render a PDF page into a canvas at the given CSS scale (backing store scaled by
 * capped DPR). Returns { task, width, height } where task is the cancelable RenderTask.
 */
export function renderPageInto(page, scale, canvas) {
  const dpr = dprScale();
  const cssVp = page.getViewport({ scale });
  const renderVp = page.getViewport({ scale: scale * dpr });
  canvas.width = Math.max(1, Math.floor(renderVp.width));
  canvas.height = Math.max(1, Math.floor(renderVp.height));
  canvas.style.width = `${Math.floor(cssVp.width)}px`;
  canvas.style.height = `${Math.floor(cssVp.height)}px`;
  const task = page.render({
    canvasContext: canvas.getContext('2d', { alpha: false }),
    viewport: renderVp,
  });
  return { task, width: cssVp.width, height: cssVp.height };
}

export function isCancel(err) {
  return err && err.name === 'RenderingCancelledException';
}
