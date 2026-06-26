import Link from 'next/link';

import { getChannels, getLiveKindFeed, type BroadcastCard } from '@/lib/broadcast';

// شريط «بثوث أخرى» الجانبيّ (نمط يوتيوب): بطاقات أفقيّة (مصغّرة + عنوان + حالة) للبثوث الحيّة/
// المجدوَلة والقنوات، باستثناء البثّ الحاليّ. يعيد استخدام نقاط القائمة (صفر باك إند جديد). فارغ ⇒ لا شيء.
export async function BroadcastSuggestions({ excludeId }: { excludeId: number }) {
  const [live, tv, radio] = await Promise.all([getLiveKindFeed(), getChannels('tv'), getChannels('radio')]);

  const seen = new Set<number>([excludeId]);
  const items: BroadcastCard[] = [];
  for (const b of [...live, ...tv, ...radio]) {
    if (seen.has(b.id)) continue;
    seen.add(b.id);
    items.push(b);
  }
  if (items.length === 0) return null;

  return (
    <div dir="rtl">
      <h2 className="mb-3 text-base font-extrabold text-fg">بثوث أخرى</h2>
      <div className="flex flex-col gap-3">
        {items.slice(0, 12).map((b) => (
          <Link key={b.id} href={b.href} className="group flex gap-3">
            <div className="relative aspect-video w-2/5 shrink-0 overflow-hidden border border-border bg-surface-2">
              {b.shareImage ? (
                // eslint-disable-next-line @next/next/no-img-element -- مصغّرة البثّ (تحميل كسول)
                <img src={b.shareImage} alt="" aria-hidden loading="lazy" decoding="async" className="absolute inset-0 size-full object-cover transition-transform duration-200 group-hover:scale-105" />
              ) : null}
              {b.status === 'live' ? (
                <span className="absolute end-1 top-1 inline-flex items-center gap-0.5 bg-primary px-1 py-0.5 text-[9px] font-bold text-white">
                  <span className="size-1 animate-pulse rounded-full bg-white" aria-hidden /> مباشر
                </span>
              ) : null}
            </div>
            <div className="min-w-0 flex-1">
              <span className="line-clamp-2 text-sm font-bold leading-snug text-fg group-hover:text-primary">{b.title}</span>
              {b.category ? <span className="mt-1 block text-xs text-muted">{b.category.name}</span> : null}
            </div>
          </Link>
        ))}
      </div>
    </div>
  );
}
