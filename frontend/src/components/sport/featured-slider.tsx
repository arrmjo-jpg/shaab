'use client';

import { useRef, useState } from 'react';
import Link from 'next/link';
import { BarChart3, ChevronLeft, ChevronRight, LayoutGrid, Users } from 'lucide-react';
import { Countdown } from '@/components/sport/countdown';
import type { FeaturedMatch, MatchSide } from '@/lib/sport/games';

// سلايدر المباريات المميّزة — شكل 365 featured-games-widget بالكامل: بطولة + LIVE + حالة + نتيجة/عدّاد +
// شعاران دائريّان **فوق طرفَي الشريط** + اسمان على الشريط (padding يتجاوز الشعار ⇒ لا قصّ) + VS + ملعب + ٤ أزرار
// (مواجهات/إحصائيات/تشكيلة/صفحة المباراة → تبويبات /sport/match/[id]). أعلى الشريحة رابط لصفحة المباراة. أسهم/نقاط/سحب.
export function FeaturedSlider({ matches }: { matches: FeaturedMatch[] }) {
  const [i, setI] = useState(0);
  const startX = useRef(0);
  const didSwipe = useRef(false);
  if (!matches.length) return null;
  const n = matches.length;
  const idx = ((i % n) + n) % n;
  const m = matches[idx];
  const go = (d: number) => setI((p) => ((((p + d) % n) + n) % n));

  return (
    <section dir="rtl" className="relative overflow-hidden bg-[#0f1622] text-white">
      {n > 1 && (
        <>
          <button
            type="button"
            onClick={() => go(-1)}
            aria-label="المباراة السابقة"
            className="avatar absolute start-1.5 top-[5.5rem] z-20 flex size-9 items-center justify-center rounded-full bg-white/10 text-white/90 transition-colors hover:bg-white/20"
          >
            <ChevronRight className="size-5" />
          </button>
          <button
            type="button"
            onClick={() => go(1)}
            aria-label="المباراة التالية"
            className="avatar absolute end-1.5 top-[5.5rem] z-20 flex size-9 items-center justify-center rounded-full bg-white/10 text-white/90 transition-colors hover:bg-white/20"
          >
            <ChevronLeft className="size-5" />
          </button>
        </>
      )}

      <div
        className="px-12 pb-3 pt-4"
        onTouchStart={(e) => {
          startX.current = e.touches[0].clientX;
          didSwipe.current = false;
        }}
        onTouchEnd={(e) => {
          const dx = e.changedTouches[0].clientX - startX.current;
          if (Math.abs(dx) > 40) {
            didSwipe.current = true;
            go(dx > 0 ? -1 : 1); // RTL: سحب لليسار ⇒ التالي
          }
        }}
      >
        <Slide m={m} didSwipe={didSwipe} />
      </div>

      {n > 1 && (
        <div className="flex items-center justify-center gap-1.5 pb-3">
          {matches.map((mm, k) => (
            <button
              key={mm.id}
              type="button"
              onClick={() => setI(k)}
              aria-label={`المباراة ${k + 1}`}
              aria-current={k === idx ? 'true' : undefined}
              className={'avatar size-1.5 rounded-full transition-colors ' + (k === idx ? 'bg-white' : 'bg-white/30 hover:bg-white/50')}
            />
          ))}
        </div>
      )}
    </section>
  );
}

function Slide({ m, didSwipe }: { m: FeaturedMatch; didSwipe: React.RefObject<boolean> }) {
  const isLive = m.kind === 'live';
  const hasScore = m.home.score !== null && m.away.score !== null;
  const [barHome, barAway] = barColors(m.home.color, m.away.color);
  // إخفاء تدريجيّ للأطراف ⇒ لا حافّة/زاوية حادّة، واللون يتلاشى في الخلفيّة الداكنة قبل الآخر.
  const edgeFade = 'linear-gradient(to right, transparent, #000 12%, #000 88%, transparent)';
  const guard = (e: React.MouseEvent) => {
    if (didSwipe.current) {
      e.preventDefault();
      didSwipe.current = false;
    }
  };

  return (
    <div>
      <Link href={`/sport/match/${m.id}`} onClick={guard} className="block">
        {/* البطولة + LIVE */}
        <div className="flex items-center justify-center gap-2">
          {m.competitionLogo && (
            // eslint-disable-next-line @next/next/no-img-element -- شعار البطولة من CDN 365
            <img src={m.competitionLogo} alt="" loading="lazy" className="size-5 object-contain" />
          )}
          <span className="truncate text-sm font-medium text-white/85">{m.competition ?? '—'}</span>
          {isLive && (
            <span className="shrink-0 bg-primary px-1.5 py-0.5 text-[10px] font-extrabold tracking-wider text-white">LIVE</span>
          )}
        </div>

        {/* الحالة + النتيجة / العدّاد */}
        <div className="mt-2 flex flex-col items-center gap-0.5">
          {isLive && (m.minute || m.statusText) && (
            <span className="text-xs font-extrabold text-primary">{m.minute ?? m.statusText}</span>
          )}
          {hasScore ? (
            <span className="flex items-center gap-2 text-4xl font-extrabold tabular-nums sm:text-5xl">
              <span>{m.home.score}</span>
              <span className="text-white/40">-</span>
              <span>{m.away.score}</span>
            </span>
          ) : m.startTime ? (
            <>
              <span className="text-2xl font-extrabold tabular-nums sm:text-3xl">
                <Countdown to={m.startTime} />
              </span>
              <span className="text-[11px] font-bold text-white/60">على بُعد</span>
            </>
          ) : (
            <span className="text-2xl font-extrabold text-white/40">—</span>
          )}
        </div>

        {/* شريط الأسماء — أطرافه **تتلاشى** إلى شفّاف (mask) فلا حافّة/زاوية حادّة ولا يصل اللون للآخر؛ + لونان متمايزان دائماً + عمق + فاصل مضيء + شارة VS دائريّة + شعاران مؤطَّران */}
        <div className="relative mt-4 h-16 sm:h-20">
          <div
            className="absolute inset-0 overflow-hidden"
            style={{
              backgroundImage: [
                'radial-gradient(55% 75% at 50% 50%, rgba(255,255,255,0.18), rgba(255,255,255,0) 72%)',
                'linear-gradient(to bottom, rgba(255,255,255,0.16), rgba(255,255,255,0) 24%, rgba(0,0,0,0.34))',
                `linear-gradient(100deg, ${barAway} 0%, ${barAway} 47%, ${barHome} 53%, ${barHome} 100%)`,
              ].join(', '),
              WebkitMaskImage: edgeFade,
              maskImage: edgeFade,
            }}
          />
          {/* خطّ إضاءة علويّ (يتلاشى مثل الشريط) + فاصل مركزيّ مضيء */}
          <div className="absolute inset-x-0 top-0 h-px bg-white/25" style={{ WebkitMaskImage: edgeFade, maskImage: edgeFade }} />
          <div
            className="absolute inset-y-2 left-1/2 w-px -translate-x-1/2 bg-white/35"
            style={{ boxShadow: '0 0 8px rgba(255,255,255,0.45)' }}
          />
          <div className="relative flex h-full items-center justify-between gap-1 px-[68px] sm:px-24">
            <span
              className="min-w-0 flex-1 truncate text-right text-sm font-extrabold drop-shadow-sm sm:text-base"
              style={{ color: textOn(barHome) }}
            >
              {m.home.name || '—'}
            </span>
            <span className="avatar relative z-10 flex size-7 shrink-0 items-center justify-center rounded-full border border-white/40 bg-black/35 text-[11px] font-extrabold text-white backdrop-blur-sm sm:size-8 sm:text-xs">
              VS
            </span>
            <span
              className="min-w-0 flex-1 truncate text-left text-sm font-extrabold drop-shadow-sm sm:text-base"
              style={{ color: textOn(barAway) }}
            >
              {m.away.name || '—'}
            </span>
          </div>
          <SlideLogo side={m.home} className="absolute start-0 top-1/2 -translate-y-1/2" />
          <SlideLogo side={m.away} className="absolute end-0 top-1/2 -translate-y-1/2" />
        </div>

        {/* الملعب */}
        {m.venue && <div className="mt-3 text-center text-xs text-white/55">{m.venue}</div>}
      </Link>

      {/* الأزرار الأربعة (تبويبات صفحة المباراة) */}
      <div className="mt-3 flex items-stretch justify-around gap-1 border-t border-white/10 pt-3">
        <TabButton gameId={m.id} tab="overview" label="صفحة المباراة" Icon={LayoutGrid} guard={guard} />
        <TabButton gameId={m.id} tab="lineup" label="تشكيلة الفريقين" Icon={Users} guard={guard} />
        <TabButton gameId={m.id} tab="stats" label="الإحصائيات" Icon={BarChart3} guard={guard} />
      </div>
    </div>
  );
}

function TabButton({
  gameId,
  tab,
  label,
  Icon,
  guard,
}: {
  gameId: number;
  tab: string;
  label: string;
  Icon: React.ComponentType<{ className?: string }>;
  guard: (e: React.MouseEvent) => void;
}) {
  const href = tab === 'overview' ? `/sport/match/${gameId}` : `/sport/match/${gameId}?tab=${tab}`;
  return (
    <Link
      href={href}
      onClick={guard}
      className="flex flex-1 flex-col items-center gap-1 px-1 py-1 text-center text-[10px] font-medium text-white/70 transition-colors hover:text-white sm:text-[11px]"
    >
      <Icon className="size-5 text-sky-400" />
      {label}
    </Link>
  );
}

function SlideLogo({ side, className }: { side: MatchSide; className: string }) {
  return (
    <div
      className={
        'avatar z-10 flex size-16 items-center justify-center overflow-hidden rounded-full border-2 border-white bg-white shadow-[0_3px_12px_rgba(0,0,0,0.5)] sm:size-20 ' +
        className
      }
    >
      {side.logo ? (
        // eslint-disable-next-line @next/next/no-img-element -- شعار 365 من CDN
        <img src={side.logo} alt="" loading="lazy" decoding="async" className="size-12 object-contain sm:size-16" />
      ) : (
        <span
          className="flex size-full items-center justify-center text-2xl font-extrabold text-white"
          style={{ backgroundColor: side.color ?? '#9aa0a6' }}
          aria-hidden
        >
          {(side.name || '?').slice(0, 1)}
        </span>
      )}
    </div>
  );
}

// لونا الشريط: لونا الفريقين الحقيقيّان إن كانا هويّة **حيّة** متمايزة، وإلا لونان داكنان أنيقان متمايزان (أزرق فاتح/داكن).
// 365 يعطي الرياضات الفرديّة (تنس) لونَي حشو باهتَين ثابتَين (#7f97ab/#c7d0d8) ⇒ يُعامَلان كـ«بلا هويّة» فيظهر شريط
// داكن فاخر بدل مستطيل رماديّ مسطّح. [barHome=يمين RTL، barAway=يسار].
function barColors(home?: string | null, away?: string | null): [string, string] {
  const FB: [string, string] = ['#3f5a82', '#26314a'];
  const h = vivid(home) ? (home as string) : null;
  const a = vivid(away) ? (away as string) : null;
  if (h && a) return dist(rgb(h)!, rgb(a)!) >= 64 ? [h, a] : FB;
  if (h) return [h, FB[1]];
  if (a) return [FB[0], a];
  return FB;
}

// لون «حيّ» = مشبَع كفاية ليكون هويّة فريق حقيقيّة (chroma كافٍ)؛ يستبعد ألوان 365 الباهتة للرياضات الفرديّة.
function vivid(hex?: string | null): boolean {
  const c = rgb(hex);
  if (!c) return false;
  return Math.max(...c) - Math.min(...c) >= 55;
}

// تحويل hex (3 أو 6 خانات) إلى [r,g,b]؛ غير صالح ⇒ null.
function rgb(hex?: string | null): [number, number, number] | null {
  if (!hex) return null;
  const h = hex.replace('#', '');
  const s = h.length === 3 ? h.split('').map((c) => c + c).join('') : h;
  if (s.length !== 6) return null;
  const r = parseInt(s.slice(0, 2), 16);
  const g = parseInt(s.slice(2, 4), 16);
  const b = parseInt(s.slice(4, 6), 16);
  return [r, g, b].some(Number.isNaN) ? null : [r, g, b];
}

function dist(a: [number, number, number], b: [number, number, number]): number {
  return Math.sqrt((a[0] - b[0]) ** 2 + (a[1] - b[1]) ** 2 + (a[2] - b[2]) ** 2);
}

// لون نصّ مقروء فوق لون الفريق (luminance): فاتح ⇒ نصّ داكن، داكن ⇒ أبيض. فشل ⇒ أبيض.
function textOn(hex?: string | null): string {
  const c = rgb(hex);
  if (!c) return '#ffffff';
  return 0.299 * c[0] + 0.587 * c[1] + 0.114 * c[2] > 140 ? '#14171f' : '#ffffff';
}
