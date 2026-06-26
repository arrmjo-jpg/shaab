import * as React from 'react';
import { Eye, EyeOff } from 'lucide-react';
import type { FieldError } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface PasswordFieldProps
  extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label: string;
  error?: FieldError;
}

export const PasswordField = React.forwardRef<HTMLInputElement, PasswordFieldProps>(
  ({ label, error, id, ...props }, ref) => {
    const { t } = useTranslation();
    const [show, setShow] = React.useState(false);
    const fieldId = id ?? props.name;
    return (
      <div className="space-y-1.5">
        <Label htmlFor={fieldId}>{label}</Label>
        <div className="relative">
          <Input
            ref={ref}
            id={fieldId}
            type={show ? 'text' : 'password'}
            aria-invalid={Boolean(error)}
            className="pe-10"
            {...props}
          />
          <button
            type="button"
            onClick={() => setShow((s) => !s)}
            tabIndex={-1}
            className="absolute inset-y-0 end-0 flex w-10 items-center justify-center text-muted-foreground transition-colors hover:text-foreground"
            aria-label={show ? 'hide' : 'show'}
          >
            {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
          </button>
        </div>
        {error?.message ? (
          <p className="text-xs font-medium text-destructive">{t(error.message)}</p>
        ) : null}
      </div>
    );
  },
);
PasswordField.displayName = 'PasswordField';
