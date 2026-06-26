interface DonutProps {
  success: number;
  failed: number;
  label: string;
  successLabel: string;
  failedLabel: string;
  size?: number;
}

/** donut من قطعتين (نجاح/فشل) — SVG نقي. */
export function Donut({
  success,
  failed,
  label,
  successLabel,
  failedLabel,
  size = 168,
}: DonutProps) {
  const total = success + failed;
  const rate = total > 0 ? (success / total) * 100 : 0;
  const stroke = 12;
  const r = (size - stroke) / 2;
  const c = 2 * Math.PI * r;
  const successLen = (rate / 100) * c;

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
            className="stroke-destructive/40"
          />
          <circle
            cx={size / 2}
            cy={size / 2}
            r={r}
            fill="none"
            strokeWidth={stroke}
            strokeDasharray={`${successLen} ${c - successLen}`}
            className="stroke-emerald-500 transition-all duration-700"
          />
        </svg>
        <div className="absolute inset-0 flex flex-col items-center justify-center">
          <span className="text-3xl font-extrabold tabular-nums text-emerald-500">
            {Math.round(rate)}
            <span className="text-base font-bold">%</span>
          </span>
        </div>
      </div>
      <p className="text-sm font-semibold">{label}</p>
      <div className="flex gap-4 text-xs">
        <span className="flex items-center gap-1.5">
          <span className="h-2.5 w-2.5 bg-emerald-500" />
          {successLabel}: <b className="tabular-nums">{success}</b>
        </span>
        <span className="flex items-center gap-1.5">
          <span className="h-2.5 w-2.5 bg-destructive/60" />
          {failedLabel}: <b className="tabular-nums">{failed}</b>
        </span>
      </div>
    </div>
  );
}
