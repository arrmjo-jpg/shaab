import type { ReactNode } from 'react';
import { Info } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { AnalyticsRangeKey } from '@/types/analytics.types';

/**
 * عُدّة عرض التحليلات المشتركة (فيديو/بثّ) — دون مكتبة رسوم خارجية (لا تعقيد بنيوي)
 * ودون border-radius (سياسة النظام). أعمدة flex متجاوبة، أشرطة توزيع، فلتر نطاق،
 * وتنويه «غير متعقَّب» للمقاييس المؤجّلة بصدق.
 */

export const fmtNum = (n: number | null | undefined): string => Number(n ?? 0).toLocaleString('en-US');

/** مدّة بشرية مختصرة (ث → «Xس Yد» / «Yد Zث»). */
export function fmtDuration(seconds: number | null | undefined): string {
  if (seconds === null || seconds === undefined) return '—';
  const s = Math.max(0, Math.round(seconds));
  const h = Math.floor(s / 3600);
  const m = Math.floor((s % 3600) / 60);
  const sec = s % 60;
  if (h > 0) return `${h}س ${m}د`;
  if (m > 0) return `${m}د ${sec}ث`;
  return `${sec}ث`;
}

const RANGES: AnalyticsRangeKey[] = ['24h', '7d', '30d', 'custom'];

export interface RangeValue {
  range: AnalyticsRangeKey;
  from?: string;
  to?: string;
}

export function RangeFilter({
  value,
  onChange,
  labels,
}: {
  value: RangeValue;
  onChange: (v: RangeValue) => void;
  labels: Record<string, string>;
}) {
  const dateCls =
    'h-8 border border-input bg-background px-2 text-xs focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';

  return (
    <div className="flex flex-wrap items-center gap-2">
      <div className="inline-flex border border-border">
        {RANGES.map((r) => (
          <button
            key={r}
            type="button"
            onClick={() => onChange({ range: r, from: value.from, to: value.to })}
            className={cn(
              'px-3 py-1.5 text-xs font-medium transition-colors',
              value.range === r
                ? 'bg-primary text-primary-foreground'
                : 'text-muted-foreground hover:bg-muted',
            )}
          >
            {labels[r] ?? r}
          </button>
        ))}
      </div>

      {value.range === 'custom' ? (
        <div className="flex items-center gap-1.5" dir="ltr">
          <input
            type="date"
            value={value.from ?? ''}
            max={value.to || undefined}
            onChange={(e) => onChange({ range: 'custom', from: e.target.value, to: value.to })}
            className={dateCls}
          />
          <span className="text-xs text-muted-foreground">—</span>
          <input
            type="date"
            value={value.to ?? ''}
            min={value.from || undefined}
            onChange={(e) => onChange({ range: 'custom', from: value.from, to: e.target.value })}
            className={dateCls}
          />
        </div>
      ) : null}
    </div>
  );
}

export interface TrendPoint {
  label: string;
  value: number;
}

/** مخطّط أعمدة زمنيّ (أعمدة flex متجاوبة) — تلميح القيمة عند المرور، تسمية أوّل/آخر يوم. */
export function TrendChart({
  points,
  color = 'bg-primary',
  emptyLabel,
}: {
  points: TrendPoint[];
  color?: string;
  emptyLabel?: string;
}) {
  const max = Math.max(1, ...points.map((p) => p.value));
  const total = points.reduce((sum, p) => sum + p.value, 0);

  if (points.length === 0 || total === 0) {
    return (
      <div className="flex h-44 items-center justify-center border border-dashed border-border bg-muted/20 text-xs text-muted-foreground">
        {emptyLabel ?? 'لا بيانات في هذه الفترة'}
      </div>
    );
  }

  const first = points[0]?.label ?? '';
  const last = points[points.length - 1]?.label ?? '';

  return (
    <div className="space-y-2">
      <div className="flex h-44 items-end gap-px" dir="ltr">
        {points.map((p, i) => (
          <div
            key={`${p.label}-${i}`}
            className="group flex h-full flex-1 items-end"
            title={`${p.label}: ${fmtNum(p.value)}`}
          >
            <div
              className={cn('w-full min-h-[2px] transition-colors group-hover:opacity-80', color)}
              style={{ height: `${Math.max(2, Math.round((p.value / max) * 100))}%` }}
            />
          </div>
        ))}
      </div>
      <div className="flex items-center justify-between text-[10px] text-muted-foreground" dir="ltr">
        <span>{first}</span>
        <span>{last}</span>
      </div>
    </div>
  );
}

/** شريط توزيع أفقيّ (نسبة من الإجمالي) — للمصادر/التصنيفات. */
export function BarRow({
  label,
  value,
  total,
  color = 'bg-primary',
}: {
  label: string;
  value: number;
  total: number;
  color?: string;
}) {
  const pct = total > 0 ? Math.round((value / total) * 100) : 0;

  return (
    <div className="space-y-1">
      <div className="flex items-center justify-between gap-2 text-xs">
        <span className="truncate text-muted-foreground">{label}</span>
        <span className="shrink-0 font-medium tabular-nums">
          {fmtNum(value)} <span className="text-muted-foreground">({pct}%)</span>
        </span>
      </div>
      <div className="h-2 w-full bg-muted">
        <div className={cn('h-full', color)} style={{ width: `${pct}%` }} />
      </div>
    </div>
  );
}

/** تنويه مقياس مؤجّل بصدق («غير متعقَّب بعد») — لا أرقام وهمية. */
export function DeferredNotice({ title, note }: { title: string; note: string }) {
  return (
    <div className="flex items-start gap-2 border border-dashed border-border bg-muted/30 p-3 text-xs text-muted-foreground">
      <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
      <div>
        <p className="font-medium text-foreground">{title}</p>
        <p className="mt-0.5">{note}</p>
      </div>
    </div>
  );
}

/** بطاقة مقياس (KPI) — مشتركة، بلا border-radius. */
export function MetricCard({
  label,
  value,
  icon: Icon,
  tone = 'text-primary',
}: {
  label: string;
  value: string;
  icon: LucideIcon;
  tone?: string;
}) {
  return (
    <div className="flex items-center gap-3 border border-border bg-background p-4">
      <span className={cn('flex h-10 w-10 shrink-0 items-center justify-center bg-muted', tone)}>
        <Icon className="h-5 w-5" />
      </span>
      <span className="min-w-0">
        <span className="block text-2xl font-bold leading-none tabular-nums">{value}</span>
        <span className="mt-1 block truncate text-xs text-muted-foreground">{label}</span>
      </span>
    </div>
  );
}

/** لوحة قسم (header + body) — مشتركة، بلا border-radius. */
export function Panel({
  title,
  subtitle,
  icon: Icon,
  action,
  children,
}: {
  title: string;
  subtitle?: string;
  icon?: LucideIcon;
  action?: ReactNode;
  children: ReactNode;
}) {
  return (
    <section className="border border-border bg-background">
      <header className="flex items-center gap-2 border-b border-border px-4 py-3">
        {Icon ? <Icon className="h-4 w-4 text-muted-foreground" /> : null}
        <div className="min-w-0 flex-1">
          <h2 className="text-sm font-semibold">{title}</h2>
          {subtitle ? <p className="text-xs text-muted-foreground">{subtitle}</p> : null}
        </div>
        {action}
      </header>
      <div className="p-4">{children}</div>
    </section>
  );
}
