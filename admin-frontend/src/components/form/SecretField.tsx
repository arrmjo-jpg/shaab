import * as React from 'react';
import { Eye, EyeOff } from 'lucide-react';
import type { FieldError } from 'react-hook-form';
import { useTranslation } from 'react-i18next';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';

interface SecretFieldProps
  extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'type'> {
  label: string;
  configured: boolean;
  error?: FieldError;
}

/** حقل سرّي: مقنّع، شارة "مُهيّأ"، إظهار/إخفاء. فارغ = لا تغيير. */
export const SecretField = React.forwardRef<HTMLInputElement, SecretFieldProps>(
  ({ label, configured, error, id, ...props }, ref) => {
    const { t } = useTranslation();
    const [show, setShow] = React.useState(false);
    const fieldId = id ?? props.name;
    return (
      <div className="space-y-1.5">
        <div className="flex items-center justify-between">
          <Label htmlFor={fieldId}>{label}</Label>
          {configured ? (
            <Badge variant="success">{t('settings:common.configured')}</Badge>
          ) : (
            <Badge variant="muted">{t('settings:common.notConfigured')}</Badge>
          )}
        </div>
        <div className="relative">
          <Input
            ref={ref}
            id={fieldId}
            type={show ? 'text' : 'password'}
            placeholder={configured ? '••••••••' : ''}
            autoComplete="off"
            className="pe-10"
            {...props}
          />
          <button
            type="button"
            tabIndex={-1}
            onClick={() => setShow((s) => !s)}
            className="absolute inset-y-0 end-0 flex w-10 items-center justify-center text-muted-foreground hover:text-foreground"
          >
            {show ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
          </button>
        </div>
        <p className="text-xs text-muted-foreground">{t('settings:common.secretHint')}</p>
        {error?.message ? (
          <p className="text-xs font-medium text-destructive">{t(error.message)}</p>
        ) : null}
      </div>
    );
  },
);
SecretField.displayName = 'SecretField';
