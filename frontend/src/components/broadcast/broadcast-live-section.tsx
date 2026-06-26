import Link from 'next/link';
import { Bell, Play, Radio, Tv } from 'lucide-react';
import type { ReactNode } from 'react';

import { getChannels, getLiveNow, getNextUpcoming, type BroadcastCard } from '@/lib/broadcast';

import { BroadcastCountdown } from './broadcast-countdown';
import { BroadcastGrid } from './broadcast-grid';

// قسم «البث المباشر» في الهوم — يُبرَز أعلى المحتوى عند وجود بثّ حيّ. تكيّفيّ وصادق:
//   live الآن  ⇒ بطل (صورة + راية مباشر + «شاهد الآن») — لا مشغّل في الهوم (أداء).
//   لا live    ⇒ أقرب مجدوَل + عدّ تنازليّ + رابط التفاصيل/التذكير.
//   ثمّ قنوات tv / محطّات radio (إن وُجدت). لا شيء ⇒ لا يُعرَض القسم (يُخفى).
function fmtTime(iso: string | null): string {
  if (!iso) return '';
  try {
    return new Intl.DateTimeFormat('ar-EG', { weekday: 'long', day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit' }).format(new Date(iso));
  } catch {
    return '';
  }
}

function LiveDot() {
  return <span className="inline-block size-2 shrink-0 animate-pulse rounded-full bg-white" aria-hidden />;
}

function ChannelRow({ title, icon, items }: { title: string; icon: ReactNode; items: BroadcastCard[] }) {
  return (
    <div className="mt-6">
      <div className="mb-3 flex items-center gap-2 text-sm font-bold text-fg">
        {icon}
        {title}
      </div>
      <BroadcastGrid items={items} />
    </div>
  );
}

export async function BroadcastLiveSection() {
  const live = await getLiveNow();
  const upcoming = live.length === 0 ? await getNextUpcoming() : null;
  const tv = await getChannels('tv');
  const radio = await getChannels('radio');

  if (live.length === 0 && upcoming === null && tv.length === 0 && radio.length === 0) {
    return (
      <div dir="rtl" className="flex flex-col items-center justify-center border border-dashed border-border bg-surface-2 px-6 py-20 text-center">
        <p className="text-sm text-muted">لا يوجد بثّ مباشر حالياً.</p>
      </div>
    );
  }

  const hero = live[0] ?? null;
  const moreLive = live.slice(1); // كلّ البثّ المباشر بعد البطل (بلا قصّ) — العرض تدريجيّ في BroadcastGrid.

  return (
    <section dir="rtl" aria-label="البث المباشر">
      {hero ? (
        <>
          <div className="mb-4 flex items-center gap-2">
            <span className="inline-flex items-center gap-1.5 bg-primary px-2.5 py-1 text-xs font-bold text-white">
              <LiveDot /> مباشر الآن
            </span>
            {live.length > 1 ? <span className="text-sm text-muted">{live.length} بثوث مباشرة</span> : null}
          </div>

          <Link href={hero.href} className="group relative block aspect-video w-full overflow-hidden border border-border bg-[#14161b] sm:aspect-[21/9]">
            {hero.shareImage ? (
              // eslint-disable-next-line @next/next/no-img-element -- بوستر البثّ الحيّ (lead، تحميل فوريّ)
              <img src={hero.shareImage} alt={hero.title} loading="eager" fetchPriority="high" decoding="async" className="absolute inset-0 size-full object-cover" />
            ) : null}
            <span className="absolute inset-0 bg-gradient-to-t from-black/85 via-black/30 to-black/10" aria-hidden />

            <span className="absolute end-3 top-3 inline-flex items-center gap-1.5 bg-primary px-2.5 py-1 text-xs font-bold text-white"><LiveDot /> مباشر</span>
            {hero.viewerCount > 0 ? (
              <span className="absolute start-3 top-3 inline-flex items-center gap-1 bg-black/55 px-2 py-1 text-xs font-medium text-white">
                {hero.viewerCount.toLocaleString('ar-EG')} مشاهد
              </span>
            ) : null}

            <span className="absolute inset-0 flex items-center justify-center">
              <span className="inline-flex size-16 items-center justify-center rounded-full bg-primary/90 text-white transition-transform duration-200 group-hover:scale-110">
                <Play className="size-7 translate-x-0.5" aria-hidden />
              </span>
            </span>

            <div className="absolute inset-x-0 bottom-0 p-4 sm:p-6">
              {hero.category ? <span className="text-xs font-bold text-white/80">{hero.category.name}</span> : null}
              <h2 className="mt-1 line-clamp-2 text-lg font-extrabold text-white sm:text-2xl">{hero.title}</h2>
              <span className="mt-3 inline-flex items-center gap-2 bg-white px-4 py-2 text-sm font-bold text-black">
                <Play className="size-4" aria-hidden /> شاهد الآن
              </span>
            </div>
          </Link>

          {moreLive.length ? (
            <div className="mt-4">
              <BroadcastGrid items={moreLive} />
            </div>
          ) : null}
        </>
      ) : upcoming ? (
        <div className="flex flex-col gap-4 border border-border bg-surface-2 p-5 sm:flex-row sm:items-center sm:justify-between">
          <div className="min-w-0">
            <span className="inline-flex items-center gap-1.5 border border-primary px-2 py-0.5 text-xs font-bold text-primary">
              <Bell className="size-3.5" aria-hidden /> البثّ القادم
            </span>
            {upcoming.category ? <span className="ms-2 text-xs text-muted">{upcoming.category.name}</span> : null}
            <h2 className="mt-2 line-clamp-2 text-lg font-extrabold text-fg">{upcoming.title}</h2>
            <p className="mt-1 text-sm text-muted">{fmtTime(upcoming.scheduledAt)}</p>
          </div>
          <div className="flex shrink-0 flex-col items-start gap-2 sm:items-end">
            {upcoming.scheduledAt ? <BroadcastCountdown target={upcoming.scheduledAt} className="text-2xl font-extrabold text-primary" /> : null}
            <Link href={upcoming.href} className="inline-flex items-center gap-2 bg-primary px-4 py-2 text-sm font-bold text-white">
              <Bell className="size-4" aria-hidden /> التفاصيل والتذكير
            </Link>
          </div>
        </div>
      ) : null}

      {tv.length ? <ChannelRow title="قنوات" icon={<Tv className="size-4 text-primary" aria-hidden />} items={tv} /> : null}
      {radio.length ? <ChannelRow title="محطات الراديو" icon={<Radio className="size-4 text-primary" aria-hidden />} items={radio} /> : null}
    </section>
  );
}
