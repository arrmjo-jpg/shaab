import { CloudOff } from 'lucide-react';
import type { Metadata } from 'next';

import { Container } from '@/components/layout/container';
import { AnimatedWeatherIcon } from '@/components/weather/animated-weather-icon';
import { WeatherCard } from '@/components/weather/weather-card';
import { getAllGovernoratesWeatherFull, getGovernorateWeatherFull, type WeatherFull } from '@/lib/weather';
import { weatherGradient } from '@/lib/weather-theme';

// صفحة حالة الطقس — بطاقة عصريّة تفاعليّة (موقع + تنقّل + ساعيّ + أسبوعيّ) بطلاً + شبكة كلّ المحافظات الـ12
// **مع توقّعها الأسبوعيّ**. Server Component (المفتاح خادميّ)، ISR 900s. كلّها فشلت ⇒ حالة فارغة صادقة.
export const revalidate = 900;

export const metadata: Metadata = {
  title: 'حالة الطقس في الأردن',
  description: 'حالة الطقس الحاليّة والتوقّع الأسبوعيّ في محافظات المملكة الأردنية الهاشمية الـ12 — حسب موقعك.',
};

export default async function WeatherPage() {
  const [initial, all] = await Promise.all([getGovernorateWeatherFull('amman'), getAllGovernoratesWeatherFull()]);

  return (
    <div dir="rtl">
      <div className="border-b border-border bg-surface-2">
        <Container className="py-9">
          <h1 className="text-3xl font-black tracking-tight text-fg sm:text-4xl">حالة الطقس في الأردن</h1>
          <p className="mt-2 text-muted">حالتك الحاليّة والتوقّع الأسبوعيّ لكلّ المحافظات — حسب موقعك.</p>
        </Container>
      </div>

      <Container className="py-8 sm:py-10">
        {/* البطاقة العصريّة (البطل) — بعرض الموقع الكامل، عمودان يملآن المساحة */}
        <WeatherCard initial={initial} />

        {all.length > 0 ? (
          <section className="mt-12" aria-label="كل المحافظات">
            <h2 className="mb-5 flex items-center gap-3 text-xl font-extrabold text-fg sm:text-2xl">
              <span className="h-6 w-1.5 shrink-0 bg-primary" aria-hidden />
              كل المحافظات · التوقّع الأسبوعيّ
            </h2>
            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
              {all.map((w) => (
                <GovernorateCard key={w.govId} w={w} />
              ))}
            </div>
          </section>
        ) : (
          !initial && (
            <div className="mt-10 flex min-h-[30vh] flex-col items-center justify-center gap-3 text-center">
              <CloudOff className="size-14 text-muted" aria-hidden />
              <p className="text-lg font-bold text-fg">تعذّر جلب حالة الطقس حالياً</p>
              <p className="text-muted">يُرجى المحاولة لاحقاً.</p>
            </div>
          )
        )}
      </Container>
    </div>
  );
}

// بطاقة محافظة عصريّة — تدرّج حسب الحالة/الوقت + أيقونة متحرّكة + **شريط توقّع أسبوعيّ** (٥ أيّام). زوايا inline.
function GovernorateCard({ w }: { w: WeatherFull }) {
  const c = w.current;
  const strip = w.daily.slice(0, 5);
  return (
    <article
      style={{ borderRadius: 22, backgroundImage: weatherGradient(c.icon), boxShadow: '0 16px 40px -18px rgba(8,30,60,0.5)' }}
      className="relative overflow-hidden p-4 text-white"
    >
      <div
        className="pointer-events-none absolute inset-0"
        style={{ background: 'radial-gradient(120% 70% at 50% -10%, rgba(255,255,255,0.18), transparent 60%)' }}
        aria-hidden
      />
      <div className="relative">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <h3 className="text-base font-extrabold">{c.city}</h3>
            {c.description && <p className="mt-0.5 line-clamp-1 text-xs text-white/80">{c.description}</p>}
          </div>
          <AnimatedWeatherIcon code={c.icon} className="size-12 shrink-0" title={c.description} />
        </div>

        <div className="mt-1 flex items-end gap-2">
          <span className="text-4xl font-thin leading-none tabular-nums">{c.temp}°</span>
          <span className="mb-1 text-xs tabular-nums text-white/80">
            {c.tempMax}° / {c.tempMin}°
          </span>
        </div>

        {strip.length > 0 && (
          <div
            className="mt-3 grid gap-1 border-t border-white/20 pt-3"
            style={{ gridTemplateColumns: `repeat(${strip.length}, minmax(0, 1fr))` }}
          >
            {strip.map((d) => (
              <div key={d.date} className="flex flex-col items-center gap-0.5">
                <span className="text-[10px] text-white/80">{d.dayLabel}</span>
                <AnimatedWeatherIcon code={d.icon} className="size-6" />
                <span className="text-[11px] font-bold tabular-nums">{d.tempMax}°</span>
              </div>
            ))}
          </div>
        )}
      </div>
    </article>
  );
}
