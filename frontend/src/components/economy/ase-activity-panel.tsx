'use client';

import { useState } from 'react';
import { Bar, BarChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

import type { AseActiveStock, AseMover } from '@/lib/ase-market';

const GREEN = '#16a34a';
const RED = '#dc2626';
const BLUE = '#1d4ed8';

type Tab = 'advancers' | 'decliners' | 'active';

const fmtInt = (n: number): string => new Intl.NumberFormat('en-US').format(Math.round(n));
const fmtCompact = (n: number): string => new Intl.NumberFormat('en-US', { notation: 'compact' }).format(n);

// نشاط السوق — تبويبات (الأكثر ارتفاعاً/انخفاضاً/تداولاً). كلّ تبويب: مخطّط أشرطة (recharts) + قائمة.
// عزل فشل: تبويب بلا بيانات ⇒ حالة فارغة صادقة (لا تلفيق).
export function AseActivityPanel({
  advancers,
  decliners,
  mostActive,
}: {
  advancers: AseMover[] | null;
  decliners: AseMover[] | null;
  mostActive: AseActiveStock[];
}) {
  const [tab, setTab] = useState<Tab>('advancers');
  const TABS: { key: Tab; label: string }[] = [
    { key: 'advancers', label: 'الأكثر ارتفاعاً' },
    { key: 'decliners', label: 'الأكثر إنخفاضاً' },
    { key: 'active', label: 'أكثر الأسهم تداولاً' },
  ];

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

      {tab === 'advancers' && <MoversView movers={advancers} color={GREEN} />}
      {tab === 'decliners' && <MoversView movers={decliners} color={RED} />}
      {tab === 'active' && <ActiveView items={mostActive} />}
    </div>
  );
}

function MoversView({ movers, color }: { movers: AseMover[] | null; color: string }) {
  if (!movers || movers.length === 0) return <Empty />;
  const data = movers.map((m) => ({ symbol: m.symbol, pct: Math.abs(m.changePct) }));

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <div className="h-64" dir="ltr">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data} margin={{ top: 8, right: 4, left: 0, bottom: 0 }}>
            <XAxis dataKey="symbol" tick={{ fontSize: 11 }} />
            <YAxis orientation="right" tick={{ fontSize: 11 }} width={40} tickFormatter={(v) => `${v}%`} />
            <Tooltip formatter={(v) => [`${Number(v).toFixed(2)}%`, 'التغيّر']} contentStyle={{ fontSize: 12, direction: 'rtl' }} />
            <Bar dataKey="pct" fill={color} isAnimationActive={false} />
          </BarChart>
        </ResponsiveContainer>
      </div>
      <ul className="divide-y divide-border border border-border">
        {movers.map((m) => (
          <li key={m.symbol} className="flex items-center justify-between px-4 py-2.5">
            <span className="font-bold text-fg">{m.symbol}</span>
            <span className="flex items-center gap-3" dir="ltr">
              <span className="text-sm tabular-nums text-muted">{m.price.toFixed(2)}</span>
              <span className="w-16 text-end text-sm font-bold tabular-nums" style={{ color }}>
                {m.changePct > 0 ? '+' : ''}
                {m.changePct.toFixed(2)}%
              </span>
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function ActiveView({ items }: { items: AseActiveStock[] }) {
  if (!items || items.length === 0) return <Empty />;
  const data = items.map((s) => ({ symbol: s.symbol, value: s.valueTraded }));

  return (
    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <div className="h-64" dir="ltr">
        <ResponsiveContainer width="100%" height="100%">
          <BarChart data={data} margin={{ top: 8, right: 4, left: 0, bottom: 0 }}>
            <XAxis dataKey="symbol" tick={{ fontSize: 11 }} />
            <YAxis orientation="right" tick={{ fontSize: 11 }} width={52} tickFormatter={(v) => fmtCompact(Number(v))} />
            <Tooltip formatter={(v) => [fmtInt(Number(v)), 'قيمة التداول']} contentStyle={{ fontSize: 12, direction: 'rtl' }} />
            <Bar dataKey="value" fill={BLUE} isAnimationActive={false} />
          </BarChart>
        </ResponsiveContainer>
      </div>
      <ul className="divide-y divide-border border border-border">
        {items.map((s) => (
          <li key={s.symbol} className="flex items-center justify-between px-4 py-2.5">
            <span className="font-bold text-fg">{s.symbol}</span>
            <span className="flex items-center gap-3" dir="ltr">
              <span className="text-sm tabular-nums text-muted">{s.price.toFixed(2)}</span>
              <span className="text-sm font-bold tabular-nums text-fg">{fmtInt(s.valueTraded)}</span>
            </span>
          </li>
        ))}
      </ul>
    </div>
  );
}

function Empty() {
  return (
    <div className="flex min-h-[140px] items-center justify-center border border-dashed border-border text-sm text-muted">
      لا بيانات متاحة حاليّاً.
    </div>
  );
}
