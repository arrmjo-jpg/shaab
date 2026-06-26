import { LineChart } from 'lucide-react';
import type { Metadata } from 'next';

import { AseActivityPanel } from '@/components/economy/ase-activity-panel';
import { AseDisclosuresPanel } from '@/components/economy/ase-disclosures-panel';
import { AseIndexChart } from '@/components/economy/ase-index-chart';
import { AseSummaryPanel } from '@/components/economy/ase-summary-panel';
import { Container } from '@/components/layout/container';
import {
  getAseAdvancers,
  getAseCirculars,
  getAseDecliners,
  getAseDisclosures,
  getAseIndexSeries,
  getAseSummary,
  type AseIndexData,
} from '@/lib/ase-market';

// صفحة بورصة عمّان — المؤشّر العام + chart + ملخّص السوق + النشاط. Server Component، ISR 300s.
// المصدر الرسميّ ASE فقط؛ كلّ بلوك معزول: فشله ⇒ حالة فارغة صادقة (صفر تلفيق).
export const revalidate = 300;

export const metadata: Metadata = {
  title: 'بورصة عمّان — المؤشّر العام وملخّص السوق',
  description:
    'مؤشّر بورصة عمّان (ASE) الحيّ، ملخّص السوق (حجم التداول وعدد الأسهم والعقود)، والشركات الأكثر ارتفاعاً وانخفاضاً وتداولاً.',
};

export default async function BoursePage() {
  const [index, summary, advancers, decliners, disclosures, circulars] = await Promise.all([
    getAseIndexSeries(),
    getAseSummary(),
    getAseAdvancers(),
    getAseDecliners(),
    getAseDisclosures(),
    getAseCirculars(),
  ]);

  const hasActivity = !!(advancers || decliners || (summary && summary.mostActive.length > 0));

  return (
    <Container className="py-8 sm:py-10">
      {/* الترويسة */}
      <div className="mb-6 flex flex-wrap items-center justify-between gap-3 border-b border-border pb-4">
        <div className="flex items-center gap-3">
          <span
            className="flex size-9 items-center justify-center bg-primary text-white"
            style={{ borderRadius: '10px' }}
            aria-hidden
          >
            <LineChart className="size-5" />
          </span>
          <h1 className="font-heading text-2xl font-extrabold text-fg sm:text-3xl">بورصة عمّان</h1>
        </div>
        {summary?.date && <span className="text-sm text-muted">آخر تحديث: {summary.date.replace('T', ' ')}</span>}
      </div>

      <div className="flex flex-col gap-8">
        {/* (1) السوق اليوم */}
        <Panel title="السوق اليوم">
          {index ? (
            <>
              <IndexHeader index={index} />
              <AseIndexChart index={index} />
            </>
          ) : (
            <Empty message="تعذّر تحميل بيانات المؤشّر حاليّاً." />
          )}
        </Panel>

        {/* (2) ملخّص السوق */}
        <Panel title="ملخّص السوق">
          {summary ? (
            <AseSummaryPanel summary={summary} />
          ) : (
            <Empty message="تعذّر تحميل ملخّص السوق حاليّاً." />
          )}
        </Panel>

        {/* (3) نشاط السوق */}
        <Panel title="نشاط السوق">
          {hasActivity ? (
            <AseActivityPanel advancers={advancers} decliners={decliners} mostActive={summary?.mostActive ?? []} />
          ) : (
            <Empty message="تعذّر تحميل نشاط السوق حاليّاً." />
          )}
        </Panel>

        {/* (4) التعاميم والإفصاحات — جدول تحميل (المصدر HTML الرسميّ من ASE) */}
        <Panel title="التعاميم والإفصاحات">
          {disclosures || circulars ? (
            <AseDisclosuresPanel disclosures={disclosures} circulars={circulars} />
          ) : (
            <Empty message="تعذّر تحميل التعاميم والإفصاحات حاليّاً." />
          )}
        </Panel>
      </div>

      <p className="mt-8 text-xs text-muted">
        المصدر: بورصة عمّان (ASE). البيانات حيّة أثناء ساعات التداول الرسميّة؛ خارجها تظهر بيانات آخر جلسة.
      </p>
    </Container>
  );
}

function Panel({ title, children }: { title: string; children: React.ReactNode }) {
  return (
    <section className="border border-border bg-surface">
      <div className="flex items-center gap-3 border-b border-border bg-surface-2 px-4 py-3">
        <span className="h-6 w-1.5 shrink-0 bg-primary" aria-hidden />
        <h2 className="font-heading text-lg font-bold text-fg">{title}</h2>
      </div>
      <div className="p-4 sm:p-5">{children}</div>
    </section>
  );
}

function IndexHeader({ index }: { index: AseIndexData }) {
  const cls = index.dir === 'up' ? 'text-green-600' : index.dir === 'down' ? 'text-primary' : 'text-muted';
  const arrow = index.dir === 'up' ? '▲' : index.dir === 'down' ? '▼' : '■';
  return (
    <div className="mb-4 flex flex-wrap items-baseline gap-x-3 gap-y-1">
      <span className="text-sm text-muted">المؤشّر العام</span>
      <span className="font-heading text-2xl font-extrabold tabular-nums text-fg" dir="ltr">
        {index.current.toFixed(2)}
      </span>
      <span className={`text-sm font-bold tabular-nums ${cls}`} dir="ltr">
        {arrow} {index.changePct > 0 ? '+' : ''}
        {index.changePct.toFixed(2)}%
      </span>
    </div>
  );
}

function Empty({ message }: { message: string }) {
  return (
    <div className="flex min-h-[160px] flex-col items-center justify-center gap-1.5 border border-dashed border-border bg-surface-2 p-8 text-center">
      <p className="text-sm font-bold text-fg">{message}</p>
      <p className="text-xs text-muted">مصدر السوق قد يكون متوقّفاً مؤقّتاً — حاول لاحقاً.</p>
    </div>
  );
}
