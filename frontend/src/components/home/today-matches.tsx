'use client';

import { CalendarDays } from 'lucide-react';
import { useState } from 'react';

import { MatchRow } from '@/components/sport/match-row';
import type { CompetitionGroup } from '@/lib/sport/games';

export interface MatchDay {
  key: string; // التاريخ YYYY-MM-DD (مفتاح فريد)
  label: string; // أمس/اليوم/غداً/بعد غد
  groups: CompetitionGroup[];
}

// ودجت هوم «الدوريات العربية» — تبويبات أمس/اليوم/غداً/بعد غد تبدّل **لحظيّاً** بين أيّام مُجلَبة خادميّاً
// مسبقاً (بلا BFF ولا حالة تحميل). يعيد استخدام MatchRow ونمط ترويسة البطولة من /sport. تصميم مربّع.
export function TodayMatches({ days, initial = 1 }: { days: MatchDay[]; initial?: number }) {
  const [active, setActive] = useState(initial);
  const day = days[active] ?? days[0];

  return (
    // الارتفاع يطابق القسم الرياضيّ تلقائيّاً: على الديسكتوب الصندوق مطلق داخل عمود نسبيّ
    // (lg:absolute inset-0) فلا يضخّم صفّ الـ grid — ارتفاع الصفّ تحدّده أعمدة الأخبار/الهيرو،
    // والصندوق يملؤه ويُمرَّر داخلياً. على الجوّال ارتفاع ثابت معقول مع سكرول.
    <div className="flex h-[480px] w-full flex-col border border-border lg:absolute lg:inset-0 lg:h-auto" dir="rtl">
      {/* الترويسة */}
      <div className="flex items-center gap-2 border-b border-border bg-surface-2 px-4 py-3">
        <CalendarDays className="size-4 shrink-0 text-primary" aria-hidden />
        <h3 className="text-sm font-extrabold text-fg">الدوريات العربية</h3>
      </div>

      {/* تبويبات الأيّام */}
      <div className="flex border-b border-border" role="tablist" aria-label="اليوم">
        {days.map((d, i) => (
          <button
            key={d.key}
            type="button"
            role="tab"
            aria-selected={i === active}
            onClick={() => setActive(i)}
            className={
              'flex-1 border-s border-border py-2 text-[12px] font-bold transition-colors first:border-s-0 ' +
              (i === active ? 'bg-primary text-white' : 'text-muted hover:bg-surface-2 hover:text-fg')
            }
          >
            {d.label}
          </button>
        ))}
      </div>

      {/* المباريات (قابلة للتمرير) أو حالة فارغة صادقة */}
      {day.groups.length === 0 ? (
        <div className="flex flex-1 flex-col items-center justify-center gap-1.5 p-6 text-center">
          <p className="text-sm font-bold text-muted">لا مباريات</p>
          <p className="text-xs leading-relaxed text-muted">لا توجد مباريات للدوريات العربية في {day.label}.</p>
        </div>
      ) : (
        <div className="min-h-0 flex-1 overflow-y-auto [scrollbar-width:thin] [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-border hover:[&::-webkit-scrollbar-thumb]:bg-muted">
          {day.groups.map((g) => (
            <div key={g.id} className="border-b border-border last:border-b-0">
              <div className="flex items-center gap-2 bg-surface-2 px-3 py-1.5">
                {g.logo ? (
                  // eslint-disable-next-line @next/next/no-img-element -- شعار البطولة من CDN 365
                  <img src={g.logo} alt="" loading="lazy" className="size-5 shrink-0 object-contain" />
                ) : (
                  <span className="h-4 w-1 shrink-0 bg-primary" aria-hidden />
                )}
                <span className="truncate text-[12px] font-medium text-fg">{g.name || '—'}</span>
              </div>
              <div>
                {g.matches.map((m) => (
                  <MatchRow key={m.id} match={m} />
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}
