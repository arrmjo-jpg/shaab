import * as React from 'react';
import type { FieldError } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface Option {
  value: string;
  label: string;
}

interface SelectFieldProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  label: string;
  options: Option[];
  error?: FieldError;
}

export const SelectField = React.forwardRef<HTMLSelectElement, SelectFieldProps>(
  ({ label, options, error, id, ...props }, ref) => {
    const { t } = useTranslation();
    const fieldId = id ?? props.name;
    return (
      <div className="space-y-1.5">
        <Label htmlFor={fieldId}>{label}</Label>
        <select
          ref={ref}
          id={fieldId}
          aria-invalid={Boolean(error)}
          className={cn(
            'flex h-11 w-full rounded-xl border border-input bg-background px-3.5 text-sm transition-colors',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            'aria-[invalid=true]:border-destructive',
          )}
          {...props}
        >
          {options.map((o) => (
            <option key={o.value} value={o.value}>
              {o.label}
            </option>
          ))}
        </select>
        {error?.message ? (
          <p className="text-xs font-medium text-destructive">{t(error.message)}</p>
        ) : null}
      </div>
    );
  },
);
SelectField.displayName = 'SelectField';
