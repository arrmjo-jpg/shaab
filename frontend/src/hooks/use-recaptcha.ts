'use client';

import { useEffect } from 'react';

type Grecaptcha = {
  ready: (cb: () => void) => void;
  execute: (siteKey: string, opts: { action: string }) => Promise<string>;
};

// Loads reCAPTCHA v3 (once) when enabled, and returns an executor that yields a token per action.
// Returns null when disabled or unavailable — callers decide whether to block submit.
export function useRecaptcha(enabled: boolean, siteKey: string | null) {
  useEffect(() => {
    if (!enabled || !siteKey) return;
    if (document.getElementById('recaptcha-v3')) return;
    const script = document.createElement('script');
    script.id = 'recaptcha-v3';
    script.src = `https://www.google.com/recaptcha/api.js?render=${siteKey}`;
    script.async = true;
    document.head.appendChild(script);
  }, [enabled, siteKey]);

  return async function getToken(action: string): Promise<string | null> {
    if (!enabled || !siteKey) return null;
    const grecaptcha = (window as unknown as { grecaptcha?: Grecaptcha }).grecaptcha;
    if (!grecaptcha) return null;
    return new Promise((resolve) => {
      grecaptcha.ready(() => {
        grecaptcha.execute(siteKey, { action }).then(resolve).catch(() => resolve(null));
      });
    });
  };
}
