'use client';

import { CalendarDays, X } from 'lucide-react';
import { useRouter } from 'next/navigation';

// منتقي تاريخ الأرشيف — يسمح بالماضي فقط (`max=today` يمنع المستقبل في المتصفّح + تحقّق خادميّ مزدوج).
// التنقّل عبر `?date=` (خادميّ، ISR) — لا جلب بيانات في العميل (حارس الأداء).
export function GoldDatePicker({ max, value }: { max: string; value: string | null }) {
  const router = useRouter();

  return (
    <div className="flex flex-wrap items-center gap-2">
      <label
        className="flex items-center gap-2 border border-border bg-surface px-3 py-2"
        style={{ borderRadius: '10px' }}
      >
        <CalendarDays className="size-4 shrink-0 text-primary" aria-hidden />
        <span className="text-sm font-bold text-fg">اختر تاريخاً</span>
        <input
          type="date"
          max={max}
          defaultValue={value ?? ''}
          aria-label="تاريخ الأرشيف"
          onChange={(e) => {
            const d = e.target.value;
            router.push(d && d <= max ? `/gold-prices?date=${d}` : '/gold-prices');
          }}
          className="bg-transparent text-sm text-fg outline-none"
        />
      </label>
      {value && (
        <button
          type="button"
          onClick={() => router.push('/gold-prices')}
          className="flex items-center gap-1 px-2 py-2 text-xs font-bold text-muted transition-colors hover:text-primary"
        >
          <X className="size-3.5" aria-hidden />
          مسح
        </button>
      )}
    </div>
  );
}
