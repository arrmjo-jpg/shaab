import { useEffect, useRef, useState } from 'react';
import { useQuery, keepPreviousData } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { BarChart3, Loader2, Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { pollsService } from '@/services/polls.service';
import type { PollData, PollState } from '@/types/polls.types';
import type { PollAttrs } from './PollExtension';

interface Props {
  open: boolean;
  onClose: () => void;
  onConfirm: (attrs: PollAttrs) => void;
}

const STATE_VARIANT: Record<PollState, 'success' | 'default' | 'muted'> = {
  open: 'success',
  scheduled: 'default',
  inactive: 'muted',
  closed: 'muted',
};

/**
 * Poll picker for the article editor. Unlike the embed dialog (which resolves a
 * URL server-side), this searches existing polls and inserts a uuid-only node:
 *   onConfirm({ uuid })
 * Mirrors EmbedDialog's modal container/markup (flat, no border-radius).
 */
export function PollDialog({ open, onClose, onConfirm }: Props) {
  const { t } = useTranslation('content');
  // poll state labels live in the shared `polls` namespace.
  const { t: tp } = useTranslation('polls');
  const inputRef = useRef<HTMLInputElement | null>(null);
  const [search, setSearch] = useState('');
  const [debounced, setDebounced] = useState('');

  useEffect(() => {
    if (open) {
      setSearch('');
      setDebounced('');
      queueMicrotask(() => inputRef.current?.focus());
    }
  }, [open]);

  useEffect(() => {
    const id = window.setTimeout(() => setDebounced(search), 350);
    return () => window.clearTimeout(id);
  }, [search]);

  const q = useQuery({
    queryKey: ['polls', 'picker', debounced],
    queryFn: () =>
      pollsService.list({
        page: 1,
        per_page: 30,
        search: debounced,
        is_active: '',
        sort: '-id',
        trashed: '',
      }),
    enabled: open,
    placeholderData: keepPreviousData,
  });

  if (!open) return null;

  const rows = (q.data?.data ?? []) as PollData[];

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-foreground/40 p-4 backdrop-blur-sm">
      <div className="w-full max-w-md border border-border bg-background p-5 shadow-soft-lg">
        <header className="mb-4 flex items-center gap-3">
          <div className="flex h-9 w-9 items-center justify-center bg-primary/10 text-primary">
            <BarChart3 className="h-5 w-5" />
          </div>
          <div>
            <h3 className="text-sm font-bold">{t('editor.poll.title')}</h3>
            <p className="text-xs text-muted-foreground">{t('editor.poll.hint')}</p>
          </div>
        </header>

        <div className="relative">
          <Search className="pointer-events-none absolute inset-y-0 start-3 my-auto h-4 w-4 text-muted-foreground" />
          <input
            ref={inputRef}
            type="text"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Escape') onClose();
            }}
            placeholder={t('editor.poll.searchPlaceholder')}
            className="h-10 w-full border border-input bg-background ps-9 pe-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          />
        </div>

        <div className="mt-3 max-h-[320px] overflow-y-auto border border-border bg-background">
          {q.isLoading ? (
            <div className="flex items-center justify-center gap-2 p-6 text-sm text-muted-foreground">
              <Loader2 className="h-4 w-4 animate-spin" />
              <span>{t('editor.poll.loading')}</span>
            </div>
          ) : q.isError ? (
            <p className="p-6 text-center text-sm text-destructive">{t('editor.poll.error')}</p>
          ) : rows.length === 0 ? (
            <p className="p-6 text-center text-sm text-muted-foreground">{t('editor.poll.empty')}</p>
          ) : (
            rows.map((p) => (
              <button
                key={p.id}
                type="button"
                onClick={() => onConfirm({ uuid: p.uuid })}
                className={cn(
                  'flex w-full items-start gap-3 border-b border-border px-3 py-2.5 text-start transition-colors last:border-0',
                  'hover:bg-accent/40',
                )}
              >
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium">{p.question}</p>
                </div>
                <Badge variant={STATE_VARIANT[p.state]} className="shrink-0">
                  {tp(`pollState.${p.state}`)}
                </Badge>
              </button>
            ))
          )}
        </div>

        <div className="mt-5 flex items-center justify-end gap-2">
          <Button type="button" variant="ghost" onClick={onClose}>
            {t('articles.form.cancel')}
          </Button>
        </div>
      </div>
    </div>
  );
}
