'use client';

import { useState } from 'react';
import { Cell, Pie, PieChart, ResponsiveContainer } from 'recharts';

import type { AseBreadth, AseSegment, AseSummary } from '@/lib/ase-market';

const GREEN = '#16a34a';
const RED = '#dc2626';
const AMBER = '#f59e0b';

const TABS = [
  { key: 'regular', label: 'السوق النظامي' },
  { key: 'otc', label: 'غير المدرجة' },
  { key: 'bonds', label: 'السندات' },
] as const;

type SegKey = (typeof TABS)[number]['key'];

const fmtInt = (n: number): string => new Intl.NumberFormat('en-US').format(Math.round(n));

// ملخّص السوق — تبويبات (نظامي/غير مدرجة/سندات) + Donut توزيع الشركات + جدول إحصائيّات.
// كلّ التبويبات من استجابة واحدة (لا طلب إضافيّ). السندات بلا توزيع ⇒ يُخفى الـDonut بصدق.
export function AseSummaryPanel({ summary }: { summary: AseSummary }) {
  const [tab, setTab] = useState<SegKey>('regular');
  const seg: AseSegment = summary[tab];

  return (
    <div>
      <div className="mb-5 flex flex-wrap gap-1 border-b border-border" role="tablist">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            role="tab"
            aria-selected={tab === t.key}
            onClick={() => setTab(t.key)}
            className={
              'border-b-2 px-3 py-2 text-sm font-bold transition-colors ' +
              (tab === t.key ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-fg')
            }
          >
            {t.label}
          </button>
        ))}
      </div>

      <div className="grid grid-cols-1 items-center gap-6 sm:grid-cols-2">
        {seg.breadth ? (
          <BreadthDonut breadth={seg.breadth} />
        ) : (
          <p className="flex min-h-[140px] items-center justify-center text-center text-sm text-muted">
            لا يوجد توزيع شركات للسندات.
          </p>
        )}
        <StatsTable seg={seg} />
      </div>
    </div>
  );
}

function BreadthDonut({ breadth }: { breadth: AseBreadth }) {
  const data = [
    { name: 'الشركات المرتفعة', value: breadth.gainers, color: GREEN },
    { name: 'الشركات المتراجعة', value: breadth.losers, color: RED },
    { name: 'الشركات المستقرّة', value: breadth.unchanged, color: AMBER },
  ].filter((d) => d.value > 0);

  if (data.length === 0) {
    return <p className="flex min-h-[140px] items-center justify-center text-sm text-muted">لا بيانات.</p>;
  }

  return (
    <div className="flex items-center gap-4">
      <div className="h-40 w-40 shrink-0" dir="ltr">
        <ResponsiveContainer width="100%" height="100%">
          <PieChart>
            <Pie data={data} dataKey="value" innerRadius={42} outerRadius={70} paddingAngle={2} isAnimationActive={false}>
              {data.map((d) => (
                <Cell key={d.name} fill={d.color} stroke="none" />
              ))}
            </Pie>
          </PieChart>
        </ResponsiveContainer>
      </div>
      <ul className="space-y-2.5 text-sm">
        {data.map((d) => (
          <li key={d.name} className="flex items-center gap-2">
            <span className="size-3 shrink-0" style={{ backgroundColor: d.color }} aria-hidden />
            <span className="text-muted">{d.name}</span>
            <span className="font-bold tabular-nums text-fg">{fmtInt(d.value)}</span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function StatsTable({ seg }: { seg: AseSegment }) {
  const rows = [
    { label: 'حجم التداول (دينار)', value: fmtInt(seg.stats.tradingValue) },
    { label: 'عدد الأسهم', value: fmtInt(seg.stats.tradingVolume) },
    { label: 'عدد العقود', value: fmtInt(seg.stats.transactions) },
    { label: 'عدد الأوراق المالية', value: fmtInt(seg.stats.securities) },
  ];

  return (
    <div className="border border-border">
      {rows.map((r, i) => (
        <div
          key={r.label}
          className={'flex items-center justify-between px-4 py-3 ' + (i % 2 === 1 ? 'bg-surface-2' : 'bg-surface')}
        >
          <span className="text-sm text-muted">{r.label}</span>
          <span className="text-sm font-bold tabular-nums text-fg" dir="ltr">
            {r.value}
          </span>
        </div>
      ))}
    </div>
  );
}
