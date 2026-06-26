import { useEffect, useMemo, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { RotateCcw, Check, AlertTriangle, Loader2 } from 'lucide-react';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { articlesService } from '@/services/articles.service';
import { cn } from '@/lib/utils';
import type { ContentLocale, SlugCheckResult } from '@/types/content.types';

/**
 * Mirror of the backend slug generator (Article::arabicSlug):
 *   - Lowercases, splits on whitespace, replaces with '-'
 *   - Keeps Unicode letters/numbers (preserves Arabic)
 *   - Drops surrounding hyphens
 */
function autoSlug(title: string): string {
  const trimmed = title.trim().toLowerCase();
  if (trimmed === '') return '';
  return trimmed
    .replace(/\s+/gu, '-')
    .replace(/[^\p{L}\p{N}-]+/gu, '')
    .replace(/^-+|-+$/gu, '')
    .replace(/-+/g, '-');
}

const SLUG_RE = /^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u;

interface Props {
  /** Live title input — used as a source for the auto-generation preview. */
  title: string;
  /** Current slug value (controlled). Empty string means "auto-generate on save". */
  value: string;
  onChange: (next: string) => void;
  error?: string;
  /** Locale + current article id — for live conflict checking against the backend. */
  locale: ContentLocale;
  articleId?: number | null;
}

export function SlugField({ title, value, onChange, error, locale, articleId }: Props) {
  const { t } = useTranslation('content');

  const preview = useMemo(() => autoSlug(title), [title]);
  const trimmed = value.trim();
  const isEmpty = trimmed === '';
  const formatOk = isEmpty || SLUG_RE.test(trimmed);

  const showResetButton = !isEmpty && preview !== '' && preview !== trimmed;
  const effective = isEmpty ? preview : trimmed;

  // ── Live conflict check (debounced) against /admin/articles/slug-check ──
  const [checking, setChecking] = useState(false);
  const [result, setResult] = useState<SlugCheckResult | null>(null);

  useEffect(() => {
    setResult(null);
    if (effective === '' || !formatOk) {
      setChecking(false);
      return;
    }
    setChecking(true);
    const handle = setTimeout(() => {
      let active = true;
      articlesService
        .slugCheck(effective, locale, articleId)
        .then((r) => {
          if (active) setResult(r);
        })
        .catch(() => {
          if (active) setResult(null);
        })
        .finally(() => {
          if (active) setChecking(false);
        });
      return () => {
        active = false;
      };
    }, 400);

    return () => clearTimeout(handle);
  }, [effective, formatOk, locale, articleId]);

  return (
    <div className="space-y-1.5">
      <div className="flex items-center justify-between gap-2">
        <Label htmlFor="article-slug">{t('articles.form.slug')}</Label>
        {showResetButton ? (
          <button
            type="button"
            onClick={() => onChange('')}
            className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            title={t('articles.form.slugRegenerate')}
          >
            <RotateCcw className="h-3 w-3" />
            {t('articles.form.slugAutoButton')}
          </button>
        ) : null}
      </div>
      <Input
        id="article-slug"
        value={value}
        onChange={(e) => onChange(e.target.value)}
        placeholder={preview || t('articles.form.slugPlaceholder')}
        dir="ltr"
        spellCheck={false}
        aria-invalid={Boolean(error) || !formatOk}
        className={cn(!formatOk && 'border-destructive focus-visible:ring-destructive')}
      />
      <div className="space-y-1">
        {effective ? (
          <p className="text-xs text-muted-foreground">
            {t('articles.form.slugPreview')}{' '}
            <span dir="ltr" className="font-mono text-foreground/80">
              /{effective}
            </span>
          </p>
        ) : null}

        {/* Live availability feedback */}
        {checking ? (
          <p className="inline-flex items-center gap-1 text-xs text-muted-foreground">
            <Loader2 className="h-3 w-3 animate-spin" />
            {t('articles.form.slugChecking')}
          </p>
        ) : result && formatOk ? (
          result.available ? (
            <p className="inline-flex items-center gap-1 text-xs text-emerald-600 dark:text-emerald-400">
              <Check className="h-3 w-3" />
              {t('articles.form.slugAvailable')}
            </p>
          ) : (
            <p className="inline-flex flex-wrap items-center gap-1.5 text-xs text-destructive">
              <AlertTriangle className="h-3 w-3 shrink-0" />
              {t('articles.form.slugTaken')}
              <button
                type="button"
                onClick={() => onChange(result.suggestion)}
                className="font-mono underline hover:no-underline"
                dir="ltr"
              >
                {t('articles.form.slugUseSuggestion', { slug: result.suggestion })}
              </button>
            </p>
          )
        ) : null}

        {isEmpty ? (
          <p className="text-xs text-muted-foreground">{t('articles.form.slugAutoHint')}</p>
        ) : !formatOk ? (
          <p className="text-xs font-medium text-destructive">{t('articles.validation.slug')}</p>
        ) : null}
        {error ? <p className="text-xs font-medium text-destructive">{t(error)}</p> : null}
      </div>
    </div>
  );
}
