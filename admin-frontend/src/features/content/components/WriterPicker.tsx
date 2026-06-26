import { useEffect, useId, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronDown, Loader2, Plus, Search, UserCircle2, X } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { useWritersSearch } from '../hooks';
import { QuickAddWriterModal } from './QuickAddWriterModal';
import type { UserData } from '@/types/users.types';

interface Props {
  value: number | null;
  onChange: (id: number | null) => void;
  /** Initial author display (used on edit before search runs). */
  initialAuthor?: { id: number; name: string; email?: string } | null;
  /** Show "+ Add writer" footer when true (gate by users.create permission). */
  canCreate: boolean;
  label: string;
  /** Optional placeholder when no writer is selected. */
  placeholder?: string;
}

/**
 * Async searchable writer picker. Replaces the static dropdown — backed by
 * the existing /admin/users endpoint with is_writer=1 + partial search.
 *
 * UX:
 *   - Button trigger shows the currently selected writer or placeholder.
 *   - On open: auto-focuses search input; fetch is gated by `open` so a closed
 *     picker never touches the network.
 *   - Debounced query (250ms) → suggestions list, keyboard-navigable.
 *   - Optional "+ Add writer" footer launches the QuickAddWriterModal.
 */
export function WriterPicker({
  value,
  onChange,
  initialAuthor,
  canCreate,
  label,
  placeholder,
}: Props) {
  const { t } = useTranslation('content');
  const listId = useId();
  const containerRef = useRef<HTMLDivElement | null>(null);
  const inputRef = useRef<HTMLInputElement | null>(null);

  const [open, setOpen] = useState(false);
  const [query, setQuery] = useState('');
  const [debounced, setDebounced] = useState('');
  const [highlight, setHighlight] = useState(0);
  const [quickAddOpen, setQuickAddOpen] = useState(false);

  // Selected display — falls back to "initialAuthor" when the picker hasn't
  // searched yet (e.g. edit mode hydration).
  const [displayCache, setDisplayCache] = useState<Map<number, UserData>>(() => new Map());
  const selectedFromCache = value !== null ? displayCache.get(value) ?? null : null;
  const selectedDisplay =
    selectedFromCache
      ? { id: selectedFromCache.id, name: selectedFromCache.name, email: selectedFromCache.email }
      : initialAuthor && initialAuthor.id === value
        ? initialAuthor
        : null;

  // Debounce the search input (350ms is too sluggish for a focused picker; 250ms is comfortable).
  useEffect(() => {
    const id = window.setTimeout(() => setDebounced(query), 250);
    return () => window.clearTimeout(id);
  }, [query]);

  const q = useWritersSearch(debounced, open);
  const results = q.data?.data ?? [];

  // Cache lookups so the trigger keeps showing the name after selection.
  useEffect(() => {
    if (results.length === 0) return;
    setDisplayCache((prev) => {
      const next = new Map(prev);
      for (const u of results) next.set(u.id, u);
      return next;
    });
  }, [results]);

  useEffect(() => {
    setHighlight(0);
  }, [debounced, open]);

  // Close on outside click + Escape on the document.
  useEffect(() => {
    if (!open) return;
    const onDocClick = (e: MouseEvent) => {
      if (!containerRef.current) return;
      if (!containerRef.current.contains(e.target as Node)) setOpen(false);
    };
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') setOpen(false);
    };
    document.addEventListener('mousedown', onDocClick);
    document.addEventListener('keydown', onKey);
    return () => {
      document.removeEventListener('mousedown', onDocClick);
      document.removeEventListener('keydown', onKey);
    };
  }, [open]);

  // Auto-focus input on open.
  useEffect(() => {
    if (!open) return;
    queueMicrotask(() => inputRef.current?.focus());
  }, [open]);

  const select = (u: UserData) => {
    setDisplayCache((prev) => new Map(prev).set(u.id, u));
    onChange(u.id);
    setOpen(false);
    setQuery('');
  };

  const clear = () => {
    onChange(null);
  };

  const onInputKey = (e: React.KeyboardEvent<HTMLInputElement>) => {
    if (results.length === 0) return;
    if (e.key === 'ArrowDown') {
      e.preventDefault();
      setHighlight((h) => Math.min(results.length - 1, h + 1));
    } else if (e.key === 'ArrowUp') {
      e.preventDefault();
      setHighlight((h) => Math.max(0, h - 1));
    } else if (e.key === 'Enter') {
      e.preventDefault();
      const item = results[highlight];
      if (item) select(item);
    }
  };

  return (
    <div className="space-y-1.5" ref={containerRef}>
      <Label htmlFor={`${listId}-trigger`}>{label}</Label>

      <div className="relative">
        <button
          id={`${listId}-trigger`}
          type="button"
          onClick={() => setOpen((v) => !v)}
          aria-haspopup="listbox"
          aria-expanded={open}
          aria-controls={listId}
          className={cn(
            'flex h-11 w-full items-center gap-2 border border-input bg-background px-3 text-start text-sm transition-colors',
            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring',
            open && 'ring-2 ring-ring',
          )}
        >
          <UserCircle2 className="h-4 w-4 shrink-0 text-muted-foreground" />
          {selectedDisplay ? (
            <span className="flex-1 truncate">
              <span className="font-medium">{selectedDisplay.name}</span>
              {selectedDisplay.email ? (
                <span className="ms-2 text-xs text-muted-foreground" dir="ltr">
                  {selectedDisplay.email}
                </span>
              ) : null}
            </span>
          ) : (
            <span className="flex-1 truncate text-muted-foreground">
              {placeholder ?? t('articles.form.pickAuthor')}
            </span>
          )}
          {value !== null ? (
            <button
              type="button"
              onClick={(e) => {
                e.stopPropagation();
                clear();
              }}
              className="flex h-7 w-7 shrink-0 items-center justify-center text-muted-foreground hover:text-destructive"
              aria-label={t('articles.form.author.clear')}
              tabIndex={-1}
            >
              <X className="h-4 w-4" />
            </button>
          ) : null}
          <ChevronDown
            className={cn('h-4 w-4 shrink-0 text-muted-foreground transition-transform', open && 'rotate-180')}
          />
        </button>

        {open ? (
          <div
            id={listId}
            role="listbox"
            className="absolute z-30 mt-1 w-full border border-border bg-background shadow-soft-lg"
          >
            <div className="relative border-b border-border p-2">
              <Search className="pointer-events-none absolute inset-y-0 start-4 my-auto h-4 w-4 text-muted-foreground" />
              <input
                ref={inputRef}
                type="text"
                value={query}
                onChange={(e) => setQuery(e.target.value)}
                onKeyDown={onInputKey}
                placeholder={t('articles.form.author.searchPlaceholder')}
                className="h-9 w-full border border-input bg-background ps-9 pe-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              />
            </div>

            <div className="max-h-[260px] overflow-y-auto">
              {q.isLoading && results.length === 0 ? (
                <div className="flex items-center justify-center gap-2 p-4 text-sm text-muted-foreground">
                  <Loader2 className="h-4 w-4 animate-spin" />
                  <span>{t('articles.form.author.loading')}</span>
                </div>
              ) : results.length === 0 ? (
                <p className="p-4 text-center text-sm text-muted-foreground">
                  {debounced
                    ? t('articles.form.author.empty', { query: debounced })
                    : t('articles.form.author.startTyping')}
                </p>
              ) : (
                results.map((u, i) => (
                  <button
                    key={u.id}
                    type="button"
                    role="option"
                    aria-selected={u.id === value}
                    onMouseEnter={() => setHighlight(i)}
                    onClick={() => select(u)}
                    className={cn(
                      'flex w-full items-start gap-3 border-b border-border px-3 py-2.5 text-start transition-colors last:border-0',
                      i === highlight ? 'bg-accent' : 'hover:bg-accent/60',
                      u.id === value && 'bg-primary/10',
                    )}
                  >
                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">{u.name}</p>
                      <p className="truncate text-xs text-muted-foreground" dir="ltr">
                        {u.email}
                      </p>
                    </div>
                  </button>
                ))
              )}
            </div>

            {canCreate ? (
              <button
                type="button"
                onClick={() => {
                  setQuickAddOpen(true);
                }}
                className="flex w-full items-center gap-2 border-t border-border bg-muted/30 px-3 py-2.5 text-start text-sm font-medium text-primary hover:bg-accent"
              >
                <Plus className="h-4 w-4" />
                {t('articles.form.author.addNew')}
              </button>
            ) : null}
          </div>
        ) : null}
      </div>

      <QuickAddWriterModal
        open={quickAddOpen}
        onClose={() => setQuickAddOpen(false)}
        onCreated={(writer) => {
          select(writer);
          setQuickAddOpen(false);
        }}
      />
    </div>
  );
}
