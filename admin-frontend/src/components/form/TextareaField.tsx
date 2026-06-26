import * as React from 'react';
import type { FieldError } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface TextareaFieldProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  label: string;
  error?: FieldError;
}

export const TextareaField = React.forwardRef<HTMLTextAreaElement, TextareaFieldProps>(
  ({ label, error, id, ...props }, ref) => {
    const { t } = useTranslation();
    const fieldId = id ?? props.name;
    return (
      <div className="space-y-1.5">
        <Label htmlFor={fieldId}>{label}</Label>
        <textarea
          ref={ref}
          id={fieldId}
          rows={3}
          aria-invalid={Boolean(error)}
          className={cn(
            'flex w-full rounded-xl border border-input bg-background px-3.5 py-2.5 text-sm transition-colors',
            'placeholder:text-muted-foreground/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            'aria-[invalid=true]:border-destructive',
          )}
          {...props}
        />
        {error?.message ? (
          <p className="text-xs font-medium text-destructive">{t(error.message)}</p>
        ) : null}
      </div>
    );
  },
);
TextareaField.displayName = 'TextareaField';
