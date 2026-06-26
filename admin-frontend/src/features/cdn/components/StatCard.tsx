import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

interface StatCardProps {
  icon: LucideIcon;
  label: string;
  value: string | number;
  accent?: 'primary' | 'emerald' | 'destructive';
}

const ACCENT: Record<NonNullable<StatCardProps['accent']>, string> = {
  primary: 'bg-primary/10 text-primary',
  emerald: 'bg-emerald-500/10 text-emerald-500',
  destructive: 'bg-destructive/10 text-destructive',
};

export function StatCard({ icon: Icon, label, value, accent = 'primary' }: StatCardProps) {
  return (
    <div className="flex items-center gap-4 border border-border bg-background p-5">
      <div className={cn('flex h-12 w-12 items-center justify-center', ACCENT[accent])}>
        <Icon className="h-6 w-6" />
      </div>
      <div className="min-w-0">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="mt-0.5 text-2xl font-extrabold tabular-nums">{value}</p>
      </div>
    </div>
  );
}
