'use client';

import { useEffect, useRef, useState } from 'react';

import { getClientId } from '@/lib/client-id';

// طبقة عرض الإعلان الحيّة — منفذ React أمين لـ `resources/js/ads/slot.js` (الواجهة العامّة Next،
// منفصلة عن موقع Blade فلا تُحمَّل slot.js مباشرةً). عقد Cache/CDN إلزاميّ: جلب من العميل فقط عند
// التركيب، cache:'no-store'، لا SSR/ISR/CDN/تخزين — رمز الانطباع صالح ضمن نافذة الدلو الخادميّة فقط.
// الإبداع: صورة (رابط نقر موقّع) أو HTML مُعقَّم خادميًّا (HTMLPurifier) يُحقَن عبر innerHTML — لا
// iframe (وفق .ai/advertising.md §7: ثِق بالمُعقِّم+CSP). لا إعلان ⇒ يعيد null (بلا DOM/مساحة، عقد §6).

interface ServedAd {
  type: 'image' | 'html';
  width: number | null;
  height: number | null;
  render: { image_url?: string; alt?: string; html?: string };
  impression?: { token?: string };
  click?: { url?: string };
}

/** صنف جهاز خشن لتجزئة العرض (يطابق AdDeviceClass + slot.js). */
function detectDevice(): string {
  const w = window.innerWidth || document.documentElement.clientWidth || 1280;
  if (w < 768) return 'mobile';
  if (w < 1024) return 'tablet';
  return 'desktop';
}

/** لغة الصفحة من <html lang> (ar افتراضاً). */
function pageLocale(): string {
  const lang = (document.documentElement.lang || '').slice(0, 2).toLowerCase();
  return lang === 'en' ? 'en' : 'ar';
}

/** منارة تتبّع — keepalive يبقى حيًّا عبر التنقّل؛ no-store؛ تُتجاهَل الأخطاء (صامد). */
function beacon(path: string, token: string): void {
  void fetch(path, {
    method: 'POST',
    cache: 'no-store',
    keepalive: true,
    headers: { 'Content-Type': 'application/json', 'X-Client-Id': getClientId() },
    body: JSON.stringify({ token }),
  }).catch(() => {});
}

export function AdZone({ zone, className }: { zone: string; className?: string }) {
  const [ad, setAd] = useState<ServedAd | null>(null);
  const rootRef = useRef<HTMLDivElement | null>(null);

  // جلب حيّ عند التركيب فقط — no-store (عقد Cache/CDN؛ لا SSR/ISR/كاش).
  useEffect(() => {
    let alive = true;
    void (async () => {
      try {
        const query = new URLSearchParams({ locale: pageLocale(), device: detectDevice() }).toString();
        const res = await fetch(`/api/ads/serve/${encodeURIComponent(zone)}?${query}`, {
          cache: 'no-store',
          headers: { 'X-Client-Id': getClientId() },
        });
        if (!res.ok) return;
        const data: { ad?: ServedAd | null } = await res.json().catch(() => ({}));
        const served = data?.ad ?? null;
        if (alive && served && (served.type === 'image' || served.type === 'html')) {
          setAd(served);
        }
      } catch {
        /* صامد — لا يكسر الصفحة */
      }
    })();
    return () => {
      alive = false;
    };
  }, [zone]);

  // منارة الظهور — مرّة واحدة عند رؤية 50% (IntersectionObserver؛ تدهور رشيق بلا دعم).
  useEffect(() => {
    const token = ad?.impression?.token;
    const el = rootRef.current;
    if (!token || !el) return;
    if (!('IntersectionObserver' in window)) {
      beacon('/api/ads/impression', token);
      return;
    }
    const io = new IntersectionObserver(
      (entries) => {
        for (const entry of entries) {
          if (!entry.isIntersecting) continue;
          io.disconnect();
          beacon('/api/ads/impression', token);
        }
      },
      { threshold: 0.5 },
    );
    io.observe(el);
    return () => io.disconnect();
  }, [ad]);

  if (!ad) return null; // لا إعلان ⇒ بلا عنصر DOM/مساحة (عقد §6)

  if (ad.type === 'image' && ad.render.image_url) {
    const clickUrl = ad.click?.url;
    const img = (
      // eslint-disable-next-line @next/next/no-img-element -- رابط الصورة من نظام الإعلانات كما هو (لا Proxy/تحسين، عقد §5)
      <img
        src={ad.render.image_url}
        alt={ad.render.alt ?? ''}
        loading="lazy"
        decoding="async"
        width={ad.width ?? undefined}
        height={ad.height ?? undefined}
        className="block h-auto max-w-full"
      />
    );
    return (
      <div ref={rootRef} className={className} data-ad-zone={zone}>
        {clickUrl ? (
          <a href={clickUrl} target="_blank" rel="noopener noreferrer sponsored">
            {img}
          </a>
        ) : (
          img
        )}
      </div>
    );
  }

  if (ad.type === 'html' && typeof ad.render.html === 'string') {
    const token = ad.impression?.token;
    return (
      <div
        ref={rootRef}
        className={className}
        data-ad-zone={zone}
        // إبداع HTML مُعقَّم خادميًّا (بلا script/iframe/on*) — حقن آمن وفق الـSkill (لا iframe).
        dangerouslySetInnerHTML={{ __html: ad.render.html }}
        // روابط HTML تملك href الخاصّ ⇒ منارة نقر (لا تحويل موقّع) قبل التنقّل.
        onClickCapture={
          token
            ? (event) => {
                const anchor = (event.target as HTMLElement | null)?.closest?.('a[href]');
                if (anchor) beacon('/api/ads/click', token);
              }
            : undefined
        }
      />
    );
  }

  return null;
}
