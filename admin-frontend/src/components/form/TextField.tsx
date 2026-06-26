import * as React from 'react';
import type { FieldError } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

interface TextFieldProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label: string;
  error?: FieldError;
}

export const TextField = React.forwardRef<HTMLInputElement, TextFieldProps>(
  ({ label, error, id, className, ...props }, ref) => {
    const { t } = useTranslation();
    const fieldId = id ?? props.name;
    return (
      <div className="space-y-1.5">
        <Label htmlFor={fieldId}>{label}</Label>
        <Input
          ref={ref}
          id={fieldId}
          aria-invalid={Boolean(error)}
          className={className}
          {...props}
        />
        {error?.message ? (
          <p className={cn('text-xs font-medium text-destructive')}>{t(error.message)}</p>
        ) : null}
      </div>
    );
  },
);
TextField.displayName = 'TextField';
