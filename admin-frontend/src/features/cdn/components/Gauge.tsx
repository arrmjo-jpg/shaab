import { cn } from '@/lib/utils';

interface GaugeProps {
  /** 0 - 100 */
  value: number;
  label: string;
  caption?: string;
  size?: number;
}

/** حلقة تقدّم SVG نقية — تلوّن حسب القوة (أخضر/كهرماني/أحمر). */
export function Gauge({ value, label, caption, size = 168 }: GaugeProps) {
  const v = Math.max(0, Math.min(100, value));
  const stroke = 12;
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const offset = c * (1 - v / 100);

  const tone =
    v >= 80
      ? 'text-emerald-500'
      : v >= 50
        ? 'text-primary'
        : 'text-destructive';

  return (
    <div className="flex flex-col items-center gap-3">
      <div className="relative" style={{ width: size, height: size }}>
        <svg width={size} height={size} className="-rotate-90">
          <circle
            cx={size / 2}
            cy={size / 2}
            r={r}
            fill="none"
            strokeWidth={stroke}
            className="stroke-muted"
          />
          <circle
            cx={size / 2}
            cy={size / 2}
            r={r}
            fill="none"
            strokeWidth={stroke}
            strokeLinecap="butt"
            strokeDasharray={c}
            strokeDashoffset={offset}
            className={cn('stroke-current transition-all duration-700', tone)}
          />
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className={cn('text-3xl font-extrabold tabular-nums', tone)}>
            {Math.round(v)}
            <span className="text-base font-bold">%</span>
          </span>
          {caption ? (
            <span className="mt-0.5 text-[11px] text-muted-foreground">{caption}</span>
          ) : null}
        </div>
      </div>
      <p className="text-sm font-semibold">{label}</p>
    </div>
  );
}
