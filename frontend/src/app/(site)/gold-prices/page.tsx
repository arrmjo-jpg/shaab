import { Coins } from 'lucide-react';
import type { Metadata } from 'next';

import { GoldCalculator } from '@/components/economy/gold-calculator';
import { GoldDatePicker } from '@/components/economy/gold-date-picker';
import { GoldCompareTable, GoldEmpty, GoldTable } from '@/components/economy/gold-table';
import { Container } from '@/components/layout/container';
import { getGoldForDate, getLatestGold } from '@/lib/gold';

// صفحة أسعار الذهب — اليوم + أرشيف تاريخيّ + مقارنة. Server Components، ISR 300s، صفر بيانات وهميّة.
// خطّ الموقع (الجزيرة) موروث افتراضيّاً — لا تجاوز.
export const revalidate = 300;

export const metadata: Metadata = {
  title: 'أسعار الذهب في الأردن — اليوم والأرشيف',
  description: 'أسعار الذهب الحيّة في الأردن (عيار 24 و21 و18) والليرة الإنجليزيّة والرشاديّة، مع أرشيف الأسعار ومقارنة بين التواريخ.',
};

export default async function GoldPricesPage({
  searchParams,
}: {
  searchParams: Promise<{ date?: string }>;
}) {
  const sp = await searchParams;
  const today = new Date().toISOString().slice(0, 10);

  // الماضي فقط: تنسيق صحيح + ≤ اليوم (يمنع المستقبل — تحقّق خادميّ مزدوج مع `max` في المتصفّح).
  const selected =
    typeof sp.date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(sp.date) && sp.date <= today ? sp.date : null;

  const [latest, archive] = await Promise.all([
    getLatestGold(),
    selected ? getGoldForDate(selected) : Promise.resolve(null),
  ]);

  return (
    <div>
      <Container className="py-8 sm:py-10">
        {/* الترويسة */}
        <div className="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
          <div className="flex items-center gap-3">
            <span className="flex size-9 items-center justify-center bg-primary text-white" style={{ borderRadius: '10px' }} aria-hidden>
              <Coins className="size-5" />
            </span>
            <h1 className="font-heading text-2xl font-extrabold text-fg sm:text-3xl">أسعار الذهب</h1>
          </div>
          {latest?.updatedRelative && (
            <span className="text-sm text-muted">آخر تحديث: {latest.updatedRelative}</span>
          )}
        </div>

        {/* أسعار اليوم */}
        <h2 className="mb-3 font-heading text-lg font-bold text-fg">أسعار اليوم</h2>
        {latest ? (
          <GoldTable gold={latest} />
        ) : (
          <GoldEmpty message="تعذّر تحميل أسعار اليوم حاليّاً. حاول لاحقاً." />
        )}
        <p className="mt-2 text-xs text-muted">الأسعار بالدينار الأردنيّ للغرام. المصدر: رؤيا.</p>

        {/* الأرشيف والمقارنة */}
        <div className="mt-10">
          <h2 className="mb-3 font-heading text-lg font-bold text-fg">الأرشيف والمقارنة</h2>
          <GoldDatePicker max={today} value={selected} />

          <div className="mt-4">
            {selected ? (
              archive ? (
                latest ? (
                  <GoldCompareTable today={latest} archive={archive} dateLabel={selected} />
                ) : (
                  <>
                    <h3 className="mb-3 text-sm font-bold text-fg">أسعار {selected}</h3>
                    <GoldTable gold={archive} />
                  </>
                )
              ) : (
                <GoldEmpty
                  message={`تعذّر جلب أسعار ${selected} حاليّاً — مصدر الأسعار متقلّب أحياناً، أعد المحاولة أو اختر تاريخاً آخر.`}
                />
              )
            ) : (
              <p className="text-sm text-muted">اختر تاريخاً من الماضي لعرض أسعاره ومقارنتها بأسعار اليوم.</p>
            )}
          </div>
        </div>

        {/* حاسبة الذهب — تحويل ثنائيّ بين المبلغ والكمية لكلّ نوع (أسعار اليوم الحقيقيّة) */}
        {latest && (
          <div className="mt-10">
            <GoldCalculator rows={[...latest.karats, ...latest.liras]} />
          </div>
        )}
      </Container>
    </div>
  );
}
