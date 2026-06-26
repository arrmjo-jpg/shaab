import type { ReactNode } from 'react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * عناصر عرض مشتركة لنطاق مكتبة الفيديو (لوحة/تحليلات/عمليات) — مصدر واحد
 * للبطاقة واللوحة بدلاً من تكرارها في كل صفحة. بلا border-radius (سياسة النظام).
 */
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

export function Panel({
  title,
  subtitle,
  icon: Icon,
  children,
}: {
  title: string;
  subtitle?: string;
  icon?: LucideIcon;
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
      </header>
      <div className="p-4">{children}</div>
    </section>
  );
}
