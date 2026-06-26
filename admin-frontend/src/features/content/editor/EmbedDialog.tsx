import { useEffect, useRef, useState } from 'react';
import { useMutation } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { Video } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { articlesService } from '@/services/articles.service';
import type { NormalizedError } from '@/types/api';
import type { EmbedAttrs, EmbedProvider } from './EmbedExtension';

interface Props {
  open: boolean;
  onClose: () => void;
  onConfirm: (attrs: EmbedAttrs) => void;
}

export function EmbedDialog({ open, onClose, onConfirm }: Props) {
  const { t } = useTranslation('content');
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [url, setUrl] = useState('');
  const [serverError, setServerError] = useState<string | null>(null);

  const resolve = useMutation({
    mutationFn: (raw: string) => articlesService.resolveEmbed(raw),
    onError: (e: NormalizedError) => setServerError(e.message),
  });

  useEffect(() => {
    if (open) {
      setUrl('');
      setServerError(null);
      resolve.reset();
      queueMicrotask(() => inputRef.current?.focus());
    }
  }, [open, resolve]);

  if (!open) return null;

  const submit = () => {
    if (url.trim() === '') return;
    setServerError(null);
    resolve.mutate(url.trim(), {
      onSuccess: (data) => {
        onConfirm({
          provider: data.provider as EmbedProvider,
          embed_url: data.embed_url,
          id: data.id,
        });
      },
    });
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/40 p-4 backdrop-blur-sm">
      <div className="w-full max-w-md border border-border bg-background p-5 shadow-soft-lg">
        <header className="mb-4 flex items-center gap-3">
          <div className="flex h-9 w-9 items-center justify-center bg-primary/10 text-primary">
            <Video className="h-5 w-5" />
          </div>
          <div>
            <h3 className="text-sm font-bold">{t('editor.embed.title')}</h3>
            <p className="text-xs text-muted-foreground">{t('editor.embed.hint')}</p>
          </div>
        </header>

        <label className="block text-xs font-medium text-muted-foreground" htmlFor="em-url">
          {t('editor.embed.urlLabel')}
        </label>
        <input
          id="em-url"
          ref={inputRef}
          type="text"
          dir="ltr"
          value={url}
          onChange={(e) => setUrl(e.target.value)}
          onKeyDown={(e) => {
            if (e.key === 'Enter') submit();
            else if (e.key === 'Escape') onClose();
          }}
          placeholder="https://www.youtube.com/watch?v=…"
          className="mt-1 h-10 w-full border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
        />
        {serverError ? (
          <p className="mt-1 text-xs font-medium text-destructive">{serverError}</p>
        ) : null}
        <p className="mt-2 text-xs text-muted-foreground">{t('editor.embed.supported')}</p>

        <div className="mt-5 flex items-center justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            {t('articles.form.cancel')}
          </Button>
          <Button type="button" onClick={submit} disabled={resolve.isPending || url.trim() === ''}>
            {resolve.isPending ? t('editor.embed.resolving') : t('editor.embed.insert')}
          </Button>
        </div>
      </div>
    </div>
  );
}
