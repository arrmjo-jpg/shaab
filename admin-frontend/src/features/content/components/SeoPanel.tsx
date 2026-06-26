import { useTranslation } from 'react-i18next';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { ShareArticleCard } from './ShareArticleCard';

interface Props {
  title: string;
  seoTitle: string;
  seoDescription: string;
  seoKeywords: string;
  canonicalUrl: string;
  robots: string;
  excerpt: string;
  slug: string;
  coverUrl: string | null;
  locale: 'ar' | 'en';
  /** Backend canonical path (/{locale}/articles/{id}-{slug}) when editing. */
  sharePath?: string | null;
  onChange: (patch: Partial<Pick<Props,
    'seoTitle' | 'seoDescription' | 'seoKeywords' | 'canonicalUrl' | 'robots'
  >>) => void;
}

const TITLE_OPTIMAL = { min: 30, max: 60 };
const DESC_OPTIMAL = { min: 120, max: 160 };

function countTone(len: number, range: { min: number; max: number }): string {
  if (len === 0) return 'text-muted-foreground';
  if (len > range.max) return 'text-destructive';
  if (len < range.min) return 'text-amber-600 dark:text-amber-400';
  return 'text-emerald-600 dark:text-emerald-400';
}

export function SeoPanel(props: Props) {
  const { t } = useTranslation('content');
  const {
    title,
    seoTitle,
    seoDescription,
    seoKeywords,
    canonicalUrl,
    robots,
    excerpt,
    coverUrl,
    sharePath,
    onChange,
  } = props;

  const effTitle = seoTitle.trim() || title.trim();
  const effDescription = seoDescription.trim() || excerpt.trim();

  return (
    <div className="space-y-5">
      <div className="grid gap-4">
        <div className="space-y-1.5">
          <div className="flex items-center justify-between">
            <Label htmlFor="seo-title">{t('articles.form.seoTitle')}</Label>
            <span className={cn('text-xs font-medium', countTone(seoTitle.length, TITLE_OPTIMAL))}>
              {seoTitle.length} / {TITLE_OPTIMAL.max}
            </span>
          </div>
          <Input
            id="seo-title"
            value={seoTitle}
            onChange={(e) => onChange({ seoTitle: e.target.value })}
            placeholder={title || t('articles.form.seoTitlePlaceholder')}
            maxLength={255}
          />
          <p className="text-xs text-muted-foreground">
            {t('articles.form.seoTitleHint', {
              min: TITLE_OPTIMAL.min,
              max: TITLE_OPTIMAL.max,
            })}
          </p>
        </div>

        <div className="space-y-1.5">
          <div className="flex items-center justify-between">
            <Label htmlFor="seo-desc">{t('articles.form.seoDescription')}</Label>
            <span className={cn('text-xs font-medium', countTone(seoDescription.length, DESC_OPTIMAL))}>
              {seoDescription.length} / {DESC_OPTIMAL.max}
            </span>
          </div>
          <textarea
            id="seo-desc"
            rows={3}
            value={seoDescription}
            onChange={(e) => onChange({ seoDescription: e.target.value })}
            placeholder={excerpt || t('articles.form.seoDescriptionPlaceholder')}
            maxLength={1000}
            className="flex w-full border border-input bg-background px-3.5 py-2.5 text-sm transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
          />
          <p className="text-xs text-muted-foreground">
            {t('articles.form.seoDescriptionHint', {
              min: DESC_OPTIMAL.min,
              max: DESC_OPTIMAL.max,
            })}
          </p>
        </div>

        <Input
          placeholder={t('articles.form.seoKeywords')}
          value={seoKeywords}
          onChange={(e) => onChange({ seoKeywords: e.target.value })}
          maxLength={255}
        />

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label htmlFor="seo-canonical">{t('articles.form.canonicalUrl')}</Label>
            <Input
              id="seo-canonical"
              dir="ltr"
              value={canonicalUrl}
              onChange={(e) => onChange({ canonicalUrl: e.target.value })}
              placeholder="https://example.com/…"
              maxLength={255}
            />
          </div>
          <div className="space-y-1.5">
            <Label htmlFor="seo-robots">{t('articles.form.robots')}</Label>
            <Input
              id="seo-robots"
              dir="ltr"
              value={robots}
              onChange={(e) => onChange({ robots: e.target.value })}
              placeholder="index,follow"
              maxLength={50}
            />
          </div>
        </div>
      </div>

      {/* Unified editorial sharing card — preview + share actions in one place */}
      <ShareArticleCard
        title={effTitle}
        excerpt={effDescription}
        coverUrl={coverUrl}
        canonicalPath={sharePath ?? null}
      />
    </div>
  );
}
