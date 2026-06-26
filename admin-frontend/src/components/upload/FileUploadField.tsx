import * as React from 'react';
import { UploadCloud, X } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

interface FileUploadFieldProps {
  label: string;
  accept: string;
  configured: boolean;
  previewUrl?: string | null;
  hint?: string;
  onSelect: (file: File | null) => void;
}

/** منطقة سحب/إفلات راقية مع معاينة. */
export function FileUploadField({
  label,
  accept,
  configured,
  previewUrl,
  hint,
  onSelect,
}: FileUploadFieldProps) {
  const { t } = useTranslation();
  const [drag, setDrag] = React.useState(false);
  const [local, setLocal] = React.useState<string | null>(null);
  const inputRef = React.useRef<HTMLInputElement>(null);

  const pick = (file: File | null) => {
    if (local) URL.revokeObjectURL(local);
    setLocal(file ? URL.createObjectURL(file) : null);
    onSelect(file);
  };

  const preview = local ?? previewUrl ?? null;

  return (
    <div className="space-y-2">
      <div className="flex items-center justify-between">
        <p className="text-sm font-medium">{label}</p>
        {configured ? (
          <Badge variant="success">{t('settings:common.configured')}</Badge>
        ) : (
          <Badge variant="muted">{t('settings:common.notConfigured')}</Badge>
        )}
      </div>

      <div
        onDragOver={(e) => {
          e.preventDefault();
          setDrag(true);
        }}
        onDragLeave={() => setDrag(false)}
        onDrop={(e) => {
          e.preventDefault();
          setDrag(false);
          pick(e.dataTransfer.files?.[0] ?? null);
        }}
        onClick={() => inputRef.current?.click()}
        className={cn(
          'flex cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-border bg-secondary/30 px-4 py-8 text-center transition-colors',
          drag && 'border-primary bg-primary/5',
        )}
      >
        {preview ? (
          <div className="relative">
            <img src={preview} alt="" className="h-20 w-auto rounded-xl object-contain" />
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                pick(null);
              }}
              className="absolute -end-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-destructive text-destructive-foreground"
              aria-label="remove"
            >
              <X className="h-3.5 w-3.5" />
            </button>
          </div>
        ) : (
          <>
            <UploadCloud className="h-7 w-7 text-muted-foreground" />
            <p className="text-sm text-muted-foreground">{t('settings:branding.dropHint')}</p>
          </>
        )}
        <input
          ref={inputRef}
          type="file"
          accept={accept}
          className="hidden"
          onChange={(e) => pick(e.target.files?.[0] ?? null)}
        />
      </div>
      {hint ? <p className="text-xs text-muted-foreground/70">{hint}</p> : null}
    </div>
  );
}
