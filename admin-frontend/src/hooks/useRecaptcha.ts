import { useEffect, useRef, useCallback } from 'react';
import { useQuery } from '@tanstack/react-query';
import { recaptchaService } from '@/services/recaptcha.service';
import { loadRecaptcha, executeV3 } from '@/lib/recaptcha';

/**
 * reCAPTCHA عند الطلب لمسارات auth فقط.
 * - معطّلة: execute يرجع undefined (لا token — الـ backend gated).
 * - v3: تنفيذ خفي بالـ action.
 * - v2: widget يُرسَم في containerRef، execute يقرأ الردّ.
 */
export function useRecaptcha(action: string) {
  const { data } = useQuery({
    queryKey: ['recaptcha', 'config'],
    queryFn: () => recaptchaService.config(),
    staleTime: 5 * 60_000,
  });

  const enabled = Boolean(data?.enabled);
  const version = data?.version ?? 'v3';
  const siteKey = data?.site_key ?? '';

  const containerRef = useRef<HTMLDivElement | null>(null);
  const widgetIdRef = useRef<number | null>(null);

  useEffect(() => {
    if (!enabled || !siteKey) return;
    let cancelled = false;

    void loadRecaptcha(siteKey, version).then(() => {
      if (cancelled || version !== 'v2') return;
      const el = containerRef.current;
      if (el && widgetIdRef.current === null && window.grecaptcha?.render) {
        widgetIdRef.current = window.grecaptcha.render(el, { sitekey: siteKey });
      }
    });

    return () => {
      cancelled = true;
    };
  }, [enabled, siteKey, version]);

  const execute = useCallback(async (): Promise<string | undefined> => {
    if (!enabled || !siteKey) return undefined;
    if (version === 'v2') {
      return window.grecaptcha?.getResponse(widgetIdRef.current ?? undefined) ?? '';
    }
    try {
      return await executeV3(siteKey, action);
    } catch {
      return '';
    }
  }, [enabled, siteKey, version, action]);

  const resetV2 = useCallback(() => {
    if (version === 'v2' && widgetIdRef.current !== null) {
      window.grecaptcha?.reset(widgetIdRef.current);
    }
  }, [version]);

  return { enabled, isV2: enabled && version === 'v2', containerRef, execute, resetV2 };
}
