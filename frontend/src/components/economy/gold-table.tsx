import { ArrowDownRight, ArrowUpRight, Coins, Minus } from 'lucide-react';

import type { GoldPrices, GoldRow } from '@/lib/gold';

// تدرّج ذهبيّ راقٍ للميداليات واللمسات العلويّة (ليس لوناً وظيفيّاً — لمسة معدن نفيس). يُعاد استخدامه في الويدجت.
export const GOLD = 'linear-gradient(135deg, #f7e8b0 0%, #d9b44a 45%, #b8860b 100%)';
const HERO_BG = 'radial-gradient(120% 140% at 100% 0%, #2a2113 0%, #14110a 55%, #0b0906 100%)';

// «لوحة الذهب» — بطاقات فاخرة بدل الجدول المسطّح: بطاقة مرجعيّة لعيار 24 + شبكة بقيّة العيارات + الليرات.
// نفس البيانات الحقيقيّة (بيع/شراء/اتّجاه/نسبة)؛ صفر تلفيق. متجاوب بلا تمرير أفقيّ.
export function GoldTable({ gold }: { gold: GoldPrices }) {
  const [hero, ...restKarats] = gold.karats;

  return (
    <div className="flex flex-col gap-5">
      {hero && <HeroCard row={hero} updated={gold.updatedRelative} />}

      {restKarats.length > 0 && (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {restKarats.map((r) => (
            <GoldCard key={r.key} row={r} />
          ))}
        </div>
      )}

      {gold.liras.length > 0 && (
        <div>
          <h3 className="mb-3 flex items-center gap-2 text-sm font-bold text-fg">
            <Coins className="size-4" style={{ color: '#b8860b' }} aria-hidden />
            الليرات الذهبيّة
          </h3>
          <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
            {gold.liras.map((r) => (
              <GoldCard key={r.key} row={r} coin />
            ))}
          </div>
        </div>
      )}
    </div>
  );
}

// البطاقة المرجعيّة — عيار 24: خلفيّة داكنة فاخرة + ذهبيّ، السعر بحجم بارز.
function HeroCard({ row, updated }: { row: GoldRow; updated: string | null }) {
  return (
    <div
      className="relative overflow-hidden p-5 text-white shadow-lg sm:p-6"
      style={{ borderRadius: '16px', background: HERO_BG }}
    >
      <div className="pointer-events-none absolute inset-x-0 top-0 h-1" style={{ background: GOLD }} aria-hidden />
      <div className="flex flex-wrap items-center justify-between gap-5">
        <div className="flex items-center gap-4">
          <Medallion label="24" />
          <div>
            <div className="text-xs font-semibold tracking-wide" style={{ color: '#e7c668' }}>
              السعر المرجعيّ
            </div>
            <div className="text-lg font-extrabold">{row.label}</div>
            {updated && <div className="mt-0.5 text-[11px] text-white/40">آخر تحديث {updated}</div>}
          </div>
        </div>

        <div className="text-end">
          <div className="flex items-baseline justify-end gap-1.5">
            <span className="text-3xl font-extrabold tabular-nums sm:text-4xl" style={{ color: '#f3d27a' }}>
              {row.sell.toFixed(3)}
            </span>
            <span className="text-xs font-medium text-white/50">د.أ/غرام</span>
          </div>
          <div className="mt-1.5 flex items-center justify-end gap-3">
            <span className="text-xs text-white/55">شراء {row.buy.toFixed(3)}</span>
            <ChangePill up={row.up} pct={row.pct} dark />
          </div>
        </div>
      </div>
    </div>
  );
}

// بطاقة عيار/ليرة عاديّة — سطح أبيض + شريط ذهبيّ علويّ + ميدالية.
function GoldCard({ row, coin = false }: { row: GoldRow; coin?: boolean }) {
  return (
    <div
      className="group relative overflow-hidden border border-border bg-surface p-4 shadow-sm transition hover:shadow-md"
      style={{ borderRadius: '14px' }}
    >
      <div className="pointer-events-none absolute inset-x-0 top-0 h-1" style={{ background: GOLD }} aria-hidden />
      <div className="flex items-center gap-3">
        {coin ? <CoinMedallion /> : <Medallion label={row.key} small />}
        <div className="min-w-0 flex-1">
          <div className="truncate text-sm font-bold text-fg">{row.label}</div>
          <div className="flex items-baseline gap-1">
            <span className="text-xl font-extrabold tabular-nums text-fg">{row.sell.toFixed(3)}</span>
            <span className="text-[10px] text-muted">د.أ/غرام</span>
          </div>
        </div>
      </div>
      <div className="mt-3 flex items-center justify-between border-t border-border pt-2.5">
        <span className="text-xs text-muted">شراء {row.buy.toFixed(3)}</span>
        <ChangePill up={row.up} pct={row.pct} />
      </div>
    </div>
  );
}

function Medallion({ label, small = false }: { label: string; small?: boolean }) {
  return (
    <span
      className={`flex shrink-0 items-center justify-center font-extrabold text-[#3a2c08] ${
        small ? 'size-11 text-base' : 'size-14 text-xl'
      }`}
      style={{ background: GOLD, borderRadius: '9999px', boxShadow: 'inset 0 1px 2px rgba(255,255,255,.5), 0 2px 6px rgba(184,134,11,.35)' }}
      aria-hidden
    >
      {label}
    </span>
  );
}

function CoinMedallion() {
  return (
    <span
      className="flex size-11 shrink-0 items-center justify-center"
      style={{ background: GOLD, borderRadius: '9999px', boxShadow: 'inset 0 1px 2px rgba(255,255,255,.5), 0 2px 6px rgba(184,134,11,.35)' }}
      aria-hidden
    >
      <Coins className="size-5 text-[#3a2c08]" />
    </span>
  );
}

// جدول المقارنة: سعر بيع اليوم مقابل تاريخ مختار + فرق النسبة — بقشرة ذهبيّة موحّدة.
export function GoldCompareTable({
  today,
  archive,
  dateLabel,
}: {
  today: GoldPrices;
  archive: GoldPrices;
  dateLabel: string;
}) {
  const archMap = new Map<string, GoldRow>([...archive.karats, ...archive.liras].map((r) => [r.key, r]));
  const rows = [...today.karats, ...today.liras]
    .map((t) => ({ t, a: archMap.get(t.key) }))
    .filter((x): x is { t: GoldRow; a: GoldRow } => x.a !== undefined);

  if (rows.length === 0) {
    return <GoldTable gold={archive} />;
  }

  return (
    <div className="overflow-hidden border border-border bg-surface shadow-sm" style={{ borderRadius: '14px' }}>
      <div className="h-1" style={{ background: GOLD }} aria-hidden />
      <div className="overflow-x-auto">
        <table className="w-full min-w-[460px] border-collapse text-sm">
          <thead>
            <tr className="text-amber-50" style={{ background: '#17120a' }}>
              <th className="px-4 py-3 text-start font-bold">النوع (بيع)</th>
              <th className="px-4 py-3 text-center font-bold">اليوم</th>
              <th className="px-4 py-3 text-center font-bold">{dateLabel}</th>
              <th className="px-4 py-3 text-center font-bold">الفرق</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {rows.map(({ t, a }) => {
              const diff = a.sell > 0 ? ((t.sell - a.sell) / a.sell) * 100 : 0;
              const dir = diff > 0 ? 'up' : diff < 0 ? 'down' : 'flat';
              return (
                <tr key={t.key} className="even:bg-surface-2/40">
                  <td className="px-4 py-3 font-bold text-fg">{t.label}</td>
                  <td className="px-4 py-3 text-center font-extrabold tabular-nums text-fg">{t.sell.toFixed(3)}</td>
                  <td className="px-4 py-3 text-center tabular-nums text-muted">{a.sell.toFixed(3)}</td>
                  <td className="px-4 py-3">
                    <div className="flex justify-center">
                      <ChangePill dir={dir} pct={Math.abs(diff)} />
                    </div>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>
    </div>
  );
}

// حالة فارغة أنيقة (لا أرقام مُختلَقة).
export function GoldEmpty({ message }: { message: string }) {
  return (
    <div
      className="flex flex-col items-center justify-center gap-3 border border-dashed border-border bg-surface-2 px-6 py-14 text-center"
      style={{ borderRadius: '14px' }}
    >
      <span className="flex size-12 items-center justify-center opacity-80" style={{ background: GOLD, borderRadius: '9999px' }} aria-hidden>
        <Coins className="size-6 text-[#3a2c08]" />
      </span>
      <p className="max-w-md text-sm text-muted">{message}</p>
    </div>
  );
}

// حبّة تغيّر: أخضر صعود ▲ / أحمر هبوط ▼ / رماديّ ثبات. تقبل up:boolean أو dir صريح. (مُعاد استخدامها في الويدجت.)
export function ChangePill({
  up,
  dir,
  pct,
  dark = false,
}: {
  up?: boolean;
  dir?: 'up' | 'down' | 'flat';
  pct: number;
  dark?: boolean;
}) {
  // pct=0 ⇒ ثبات (رماديّ) أيّاً كان علم الاتّجاه — لا «‎-0.00%» أحمر.
  const d: 'up' | 'down' | 'flat' = dir ?? (pct === 0 ? 'flat' : up ? 'up' : 'down');
  const Icon = d === 'up' ? ArrowUpRight : d === 'down' ? ArrowDownRight : Minus;
  const tone =
    d === 'up'
      ? dark
        ? 'bg-emerald-400/15 text-emerald-300'
        : 'bg-emerald-50 text-emerald-700'
      : d === 'down'
        ? dark
          ? 'bg-rose-400/15 text-rose-300'
          : 'bg-rose-50 text-rose-700'
        : dark
          ? 'bg-white/10 text-white/60'
          : 'bg-surface-2 text-muted';
  const sign = d === 'up' ? '+' : d === 'down' ? '-' : '';

  return (
    <span
      className={`inline-flex items-center gap-1 px-2 py-0.5 text-xs font-bold tabular-nums ${tone}`}
      style={{ borderRadius: '9999px' }}
    >
      <Icon className="size-3.5 shrink-0" aria-hidden />
      {sign}
      {pct.toFixed(2)}%
    </span>
  );
}
