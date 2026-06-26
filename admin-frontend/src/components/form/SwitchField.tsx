import { useTranslation } from 'react-i18next';
import { cn } from '@/lib/utils';

interface SwitchFieldProps {
  label: string;
  description?: string;
  checked: boolean;
  onChange: (v: boolean) => void;
  disabled?: boolean;
}

export function SwitchField({ label, description, checked, onChange, disabled }: SwitchFieldProps) {
  useTranslation();
  return (
    <div className="flex items-center justify-between gap-4 rounded-2xl border border-border bg-background px-4 py-3.5">
      <div className="space-y-0.5">
        <p className="text-sm font-medium">{label}</p>
        {description ? <p className="text-xs text-muted-foreground">{description}</p> : null}
      </div>
      <button
        type="button"
        role="switch"
        aria-checked={checked}
        disabled={disabled}
        onClick={() => onChange(!checked)}
        className={cn(
          'relative h-6 w-11 shrink-0 rounded-full transition-colors disabled:opacity-50',
          checked ? 'bg-primary' : 'bg-muted',
        )}
      >
        <span
          className={cn(
            'absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all',
            checked ? 'start-0.5 translate-x-0 rtl:-translate-x-5 ltr:translate-x-5' : 'start-0.5',
          )}
        />
      </button>
    </div>
  );
}
