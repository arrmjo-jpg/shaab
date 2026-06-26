'use client';

import { ArrowRightLeft, Calculator } from 'lucide-react';
import { useState } from 'react';

import type { GoldRow } from '@/lib/gold';

// تدرّج ذهبيّ (مُعرّف محليّاً — مكوّن عميل لا يستورد من وحدة gold-table الخادميّة).
const GOLD = 'linear-gradient(135deg, #f7e8b0 0%, #d9b44a 45%, #b8860b 100%)';

type Mode = 'sell' | 'buy';

const isKarat = (key: string) => /^\d+$/.test(key);
const dec = (key: string) => (isKarat(key) ? 4 : 3); // غرام: 4 خانات، ليرة: 3

// حاسبة الذهب — ثنائيّة الاتّجاه: اختر النوع + العمليّة، ثمّ أدخِل المبلغ ⇐ تظهر الكمية، أو العكس.
// + تفصيل «بهذا المبلغ من كلّ نوع». أسعار حقيقيّة مُمرَّرة (لا جلب عميل، لا تلفيق).
export function GoldCalculator({ rows }: { rows: GoldRow[] }) {
  const first = rows[0];
  const [key, setKey] = useState(first?.key ?? '');
  const [mode, setMode] = useState<Mode>('sell');
  const [last, setLast] = useState<'amount' | 'qty'>('amount');
  const [amount, setAmount] = useState('1000');
  const [qty, setQty] = useState(() => {
    const p = first?.sell ?? 0;
    return p > 0 ? (1000 / p).toFixed(4) : '';
  });

  if (!first) return null;

  const row = rows.find((r) => r.key === key) ?? first;
  const price = row[mode];
  const lira = !isKarat(row.key);
  const unit = lira ? 'ليرة' : 'غرام';

  const toQty = (a: string, p: number, d: number) => {
    const n = Number.parseFloat(a);
    return Number.isFinite(n) && n >= 0 && p > 0 ? (n / p).toFixed(d) : '';
  };
  const toAmount = (q: string, p: number) => {
    const n = Number.parseFloat(q);
    return Number.isFinite(n) && n >= 0 && p > 0 ? (n * p).toFixed(3) : '';
  };

  const onAmount = (v: string) => {
    setLast('amount');
    setAmount(v);
    setQty(toQty(v, price, dec(row.key)));
  };
  const onQty = (v: string) => {
    setLast('qty');
    setQty(v);
    setAmount(toAmount(v, price));
  };
  // عند تبديل النوع/العمليّة: أعد حساب الحقل التابع وفق آخر حقل عدّله المستخدم.
  const applyContext = (k: string, m: Mode) => {
    const r = rows.find((x) => x.key === k) ?? first;
    const p = r[m];
    if (last === 'amount') setQty(toQty(amount, p, dec(r.key)));
    else setAmount(toAmount(qty, p));
  };
  const onKey = (k: string) => {
    setKey(k);
    applyContext(k, mode);
  };
  const onMode = (m: Mode) => {
    setMode(m);
    applyContext(key, m);
  };

  const amountNum = Number.parseFloat(amount);
  const showBreakdown = Number.isFinite(amountNum) && amountNum > 0;

  return (
    <div className="overflow-hidden border border-border bg-surface shadow-sm" style={{ borderRadius: '14px' }}>
      <div className="h-1" style={{ background: GOLD }} aria-hidden />
      <div className="p-4 sm:p-5">
        {/* العنوان */}
        <div className="mb-4 flex items-center gap-2.5">
          <span
            className="flex size-9 shrink-0 items-center justify-center text-[#3a2c08]"
            style={{ background: GOLD, borderRadius: '10px' }}
            aria-hidden
          >
            <Calculator className="size-5" />
          </span>
          <div>
            <h3 className="text-base font-extrabold text-fg sm:text-lg">حاسبة الذهب</h3>
            <p className="text-xs text-muted">احسب الكمية مقابل المبلغ — أو العكس</p>
          </div>
        </div>

        {/* النوع */}
        <span className="mb-1.5 block text-xs font-bold text-muted">النوع</span>
        <div className="mb-3 flex flex-wrap gap-2">
          {rows.map((r) => {
            const active = r.key === key;
            return (
              <button
                key={r.key}
                type="button"
                onClick={() => onKey(r.key)}
                className={`px-3 py-1.5 text-xs font-bold transition ${
                  active ? 'text-[#3a2c08]' : 'border border-border bg-surface text-fg hover:bg-surface-2'
                }`}
                style={active ? { background: GOLD, borderRadius: '9999px' } : { borderRadius: '9999px' }}
                aria-pressed={active}
              >
                {r.label}
              </button>
            );
          })}
        </div>

        {/* العمليّة */}
        <span className="mb-1.5 block text-xs font-bold text-muted">العمليّة</span>
        <div className="mb-4 inline-flex border border-border p-0.5" style={{ borderRadius: '10px' }}>
          <button
            type="button"
            onClick={() => onMode('sell')}
            className={`px-3 py-1.5 text-xs font-bold transition ${mode === 'sell' ? 'bg-primary text-white' : 'text-muted hover:text-fg'}`}
            style={{ borderRadius: '8px' }}
            aria-pressed={mode === 'sell'}
          >
            أشتري ذهباً <span className="font-medium opacity-75">(سعر البيع)</span>
          </button>
          <button
            type="button"
            onClick={() => onMode('buy')}
            className={`px-3 py-1.5 text-xs font-bold transition ${mode === 'buy' ? 'bg-primary text-white' : 'text-muted hover:text-fg'}`}
            style={{ borderRadius: '8px' }}
            aria-pressed={mode === 'buy'}
          >
            أبيع ذهبي <span className="font-medium opacity-75">(سعر الشراء)</span>
          </button>
        </div>

        {/* الحقلان (ثنائيّ الاتّجاه) */}
        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
          <Field label="المبلغ" unit="دينار" value={amount} onChange={onAmount} />
          <span className="flex shrink-0 items-center justify-center self-center text-muted sm:mb-2.5" aria-hidden>
            <ArrowRightLeft className="size-5" />
          </span>
          <Field label="الكمية" unit={unit} value={qty} onChange={onQty} />
        </div>

        {/* النتيجة + السعر المعتمد */}
        <div className="mt-4 border-t border-border pt-3 text-sm">
          {price > 0 ? (
            <p className="text-muted">
              السعر المعتمد: <span className="font-bold text-fg tabular-nums">{price.toFixed(3)}</span> د.أ/{unit} (
              {mode === 'sell' ? 'بيع' : 'شراء'})
            </p>
          ) : (
            <p className="text-muted">السعر غير متاح لهذا النوع في هذه العمليّة.</p>
          )}
          {price > 0 && amount && qty && (
            <p className="mt-1.5 font-bold text-fg">
              <span className="tabular-nums" style={{ color: '#b8860b' }}>
                {qty}
              </span>{' '}
              {unit} من {row.label} ={' '}
              <span className="tabular-nums" style={{ color: '#b8860b' }}>
                {amount}
              </span>{' '}
              دينار
            </p>
          )}
        </div>

        {/* تفصيل: بهذا المبلغ من كلّ نوع */}
        {showBreakdown && (
          <div className="mt-4 border-t border-border pt-3">
            <p className="mb-2 text-xs font-bold text-muted">
              بـ<span className="tabular-nums text-fg">{amount}</span> دينار تحصل على:
            </p>
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
              {rows.map((r) => {
                const p = r[mode];
                const g = p > 0 ? (amountNum / p).toFixed(dec(r.key)) : '—';
                const u = isKarat(r.key) ? 'غرام' : 'ليرة';
                return (
                  <div
                    key={r.key}
                    className={`flex items-center justify-between gap-1 px-2.5 py-1.5 text-xs ${r.key === key ? 'ring-1 ring-[#d9b44a]' : ''}`}
                    style={{ background: 'var(--color-surface-2)', borderRadius: '8px' }}
                  >
                    <span className="truncate font-bold text-fg">{r.label}</span>
                    <span className="shrink-0 tabular-nums text-muted">
                      <span className="font-extrabold text-fg">{g}</span> {u}
                    </span>
                  </div>
                );
              })}
            </div>
          </div>
        )}
      </div>
    </div>
  );
}

function Field({
  label,
  unit,
  value,
  onChange,
}: {
  label: string;
  unit: string;
  value: string;
  onChange: (v: string) => void;
}) {
  return (
    <label className="flex flex-1 flex-col gap-1">
      <span className="text-xs font-bold text-muted">
        {label} <span className="font-medium">({unit})</span>
      </span>
      <div
        className="flex items-center border border-border bg-surface px-3 py-2 transition focus-within:border-primary"
        style={{ borderRadius: '10px' }}
      >
        <input
          type="number"
          min="0"
          inputMode="decimal"
          autoComplete="off"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          placeholder="0"
          className="w-full bg-transparent text-lg font-extrabold tabular-nums text-fg outline-none"
        />
        <span className="shrink-0 ps-2 text-xs font-bold text-muted">{unit}</span>
      </div>
    </label>
  );
}
