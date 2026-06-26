import { Minus, TrendingDown, TrendingUp } from 'lucide-react';
import Link from 'next/link';

import type { AseTickerItem } from '@/lib/ase-market';

// شريط بورصة عمّان (ASE Market Ticker) — Server Component، Marquee بـCSS خالص (لا JS، لا مكتبة).
// المصدر الوحيد: ticker_feeds الرسميّ (يُمرَّر items). فشل/فراغ ⇒ رسالة أنيقة، لا تلفيق ولا Stack Trace.
export function AseMarketBar({ items }: { items: AseTickerItem[] | null }) {
  // حالة فشل/فراغ — رسالة بسيطة بدل أرقام مزيّفة أو إخفاء صامت.
  if (!items || items.length === 0) {
    return (
      <div
        dir="rtl"
        className="mb-6 flex h-12 items-center justify-center px-4 text-sm font-medium text-white/70 ring-1 ring-white/10 sm:mb-8"
        style={{ backgroundColor: '#070b14', borderRadius: '10px' }}
        aria-label="شريط بورصة عمّان"
      >
        بيانات السوق غير متاحة حالياً
      </div>
    );
  }

  // السرعة متناسبة مع العدد لإيقاع ثابت (لا قياس بكسل في الخادم) — أبطأ كلّما كثُرت العناصر.
  // عامل 6 = زحف هادئ جدّاً (~40px/ث) بطلب «قلّل السرعة» المتكرّر.
  const duration = Math.max(90, Math.round(items.length * 6));
  // نسختان متطابقتان: ‎translateX(0 → -50%) يلفّ بسلاسة (‎-50% = نسخة واحدة).
  const loop = [...items, ...items];

  return (
    <div
      dir="rtl"
      className="mb-6 flex items-stretch overflow-hidden text-white shadow-lg ring-1 ring-white/10 sm:mb-8"
      style={{ backgroundColor: '#070b14', borderRadius: '10px' }}
      aria-label="شريط بورصة عمّان المالي"
    >
      {/* بطاقة الهويّة: بورصة عمّان · LIVE — رابط للوحة السوق الكاملة /bourse */}
      <Link
        href="/bourse"
        className="flex shrink-0 items-center gap-2 border-e border-white/10 bg-[#0d1322] px-4 transition-colors hover:bg-[#131a2e]"
        aria-label="بورصة عمّان — لوحة السوق الكاملة"
      >
        <span className="text-sm font-extrabold">بورصة عمّان</span>
        <span
          className="flex items-center gap-1 bg-rose-500/20 px-1.5 py-0.5 text-[10px] font-extrabold tracking-wide text-rose-300"
          style={{ borderRadius: '4px' }}
        >
          <span className="size-1.5 animate-pulse bg-rose-400" style={{ borderRadius: '9999px' }} aria-hidden />
          LIVE
        </span>
      </Link>

      {/* النافذة dir=ltr لتثبيت المسار عند اليسار (مستقلّ عن RTL القسم) + المسار المتحرّك (CSS) */}
      <div className="ase-ticker-viewport relative h-12 min-w-0 flex-1 overflow-hidden" dir="ltr">
        <div
          className="ase-ticker-track absolute inset-y-0 left-0 flex w-max items-center"
          style={{ animationDuration: `${duration}s` }}
        >
          {loop.map((it, i) => (
            <TickerItem key={`${it.symbol}-${i}`} item={it} />
          ))}
        </div>
      </div>
    </div>
  );
}

function TickerItem({ item }: { item: AseTickerItem }) {
  const { symbol, name, price, changePct, dir, isIndex } = item;
  const tone = dir === 'up' ? 'text-emerald-400' : dir === 'down' ? 'text-rose-400' : 'text-white/45';
  const Icon = dir === 'up' ? TrendingUp : dir === 'down' ? TrendingDown : Minus;
  const sign = changePct > 0 ? '+' : ''; // السالب يحمل إشارته أصلاً
  // للمؤشّرات: الرمز فقط (ASE20) — اسم الـAPI «مؤشر ASE20» يكرّر شارة «مؤشّر». للأسهم: الاسم العربيّ.
  const label = isIndex ? symbol : name;

  return (
    <span className="flex items-center gap-2 whitespace-nowrap px-5" dir="rtl">
      {isIndex && (
        <span className="bg-white/10 px-1.5 py-0.5 text-[9px] font-bold tracking-wide text-white/70" style={{ borderRadius: '4px' }}>
          مؤشّر
        </span>
      )}
      <span className="text-[13px] font-bold text-white">{label}</span>
      <span className="text-[13px] font-extrabold tabular-nums text-white/90">{price}</span>
      <span className={`flex items-center gap-0.5 text-xs font-bold tabular-nums ${tone}`}>
        <Icon className="size-3.5 shrink-0" aria-hidden />
        {sign}
        {changePct.toFixed(2)}%
      </span>
      <span className="ps-3 text-white/15" aria-hidden>
        |
      </span>
    </span>
  );
}
