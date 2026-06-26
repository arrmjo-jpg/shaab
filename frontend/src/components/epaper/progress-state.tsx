// شريط تقدّم قراءة العدد — عرضيّ بحت (يُغذّى بالصفحة الحاليّة/الإجماليّ).
export function ProgressState({
  value,
  total,
  showLabel = true,
  className,
}: {
  value: number;
  total: number | null;
  showLabel?: boolean;
  className?: string;
}) {
  const pct = total && total > 0 ? Math.min(100, Math.max(0, Math.round((value / total) * 100))) : 0;
  return (
    <div className={className}>
      <div className="h-1.5 w-full overflow-hidden bg-border" role="progressbar" aria-valuenow={pct} aria-valuemin={0} aria-valuemax={100}>
        <div className="h-full bg-primary transition-[width] duration-300" style={{ width: `${pct}%` }} />
      </div>
      {showLabel ? (
        <p className="mt-1 text-xs font-medium text-muted">
          {total && total > 0 ? `صفحة ${value} من ${total} · ${pct}%` : `صفحة ${value}`}
        </p>
      ) : null}
    </div>
  );
}
