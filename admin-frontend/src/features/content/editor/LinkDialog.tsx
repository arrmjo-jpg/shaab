import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Link2 } from 'lucide-react';
import { Button } from '@/components/ui/button';

interface Props {
  open: boolean;
  initialUrl: string;
  onClose: () => void;
  onConfirm: (url: string) => void;
  onRemove?: () => void;
}

/** Allow only http(s) and mailto, matching backend TipTapSanitizer::safeUrl. */
export function isSafeLinkUrl(value: string): boolean {
  const v = value.trim();
  if (v === '') return false;
  try {
    const scheme = new URL(v).protocol.replace(':', '').toLowerCase();
    return scheme === 'http' || scheme === 'https' || scheme === 'mailto';
  } catch {
    return false;
  }
}

export function LinkDialog({ open, initialUrl, onClose, onConfirm, onRemove }: Props) {
  const { t } = useTranslation('content');
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [url, setUrl] = useState(initialUrl);
  const [touched, setTouched] = useState(false);

  useEffect(() => {
    if (open) {
      setUrl(initialUrl);
      setTouched(false);
      // Defer focus until the input is mounted
      queueMicrotask(() => inputRef.current?.focus());
    }
  }, [open, initialUrl]);

  if (!open) return null;

  const valid = isSafeLinkUrl(url);

  const confirm = () => {
    setTouched(true);
    if (!valid) return;
    onConfirm(url.trim());
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/40 p-4 backdrop-blur-sm">
      <div className="w-full max-w-md border border-border bg-background p-5 shadow-soft-lg">
        <header className="mb-4 flex items-center gap-3">
          <div className="flex h-9 w-9 items-center justify-center bg-primary/10 text-primary">
            <Link2 className="h-5 w-5" />
          </div>
          <div>
            <h3 className="text-sm font-bold">{t('editor.link.title')}</h3>
            <p className="text-xs text-muted-foreground">{t('editor.link.hint')}</p>
          </div>
        </header>

        <label className="block text-xs font-medium text-muted-foreground" htmlFor="lk-url">
          {t('editor.link.urlLabel')}
        </label>
        <input
          id="lk-url"
          ref={inputRef}
          type="text"
          dir="ltr"
          value={url}
          onChange={(e) => setUrl(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') confirm();
            else if (e.key === 'Escape') onClose();
          }}
          placeholder="https://example.com"
          className="mt-1 h-10 w-full border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />
        {touched && !valid ? (
          <p className="mt-1 text-xs font-medium text-destructive">
            {t('editor.link.invalid')}
          </p>
        ) : null}
        <p className="mt-2 text-xs text-muted-foreground">{t('editor.link.allowedSchemes')}</p>

        <div className="mt-5 flex items-center justify-end gap-2">
          {onRemove ? (
            <Button type="button" variant="outline" onClick={onRemove}>
              {t('editor.link.remove')}
            </Button>
          ) : null}
          <Button type="button" variant="ghost" onClick={onClose}>
            {t('articles.form.cancel')}
          </Button>
          <Button type="button" onClick={confirm} disabled={!valid}>
            {t('editor.link.apply')}
          </Button>
        </div>
      </div>
    </div>
  );
}
