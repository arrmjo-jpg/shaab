/* تحميل سكربت Google reCAPTCHA عند الطلب فقط (لا حقن عام). */

declare global {
  interface Window {
    grecaptcha?: {
      ready: (cb: () => void) => void;
      execute: (siteKey: string, opts: { action: string }) => Promise<string>;
      render: (el: HTMLElement, opts: { sitekey: string }) => number;
      getResponse: (widgetId?: number) => string;
      reset: (widgetId?: number) => void;
    };
  }
}

let scriptPromise: Promise<void> | null = null;

export function loadRecaptcha(siteKey: string, version: string): Promise<void> {
  if (scriptPromise) return scriptPromise;

  scriptPromise = new Promise<void>((resolve, reject) => {
    const s = document.createElement('script');
    s.async = true;
    s.defer = true;
    s.src =
      version === 'v3'
        ? `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`
        : 'https://www.google.com/recaptcha/api.js?render=explicit';
    s.onload = () => resolve();
    s.onerror = () => reject(new Error('recaptcha-load-failed'));
    document.head.appendChild(s);
  });

  return scriptPromise;
}

export function executeV3(siteKey: string, action: string): Promise<string> {
  return new Promise<string>((resolve, reject) => {
    const g = window.grecaptcha;
    if (!g) {
      reject(new Error('recaptcha-not-ready'));
      return;
    }
    g.ready(() => {
      g.execute(siteKey, { action })
        .then(resolve)
        .catch(() => reject(new Error('recaptcha-execute-failed')));
    });
  });
}
