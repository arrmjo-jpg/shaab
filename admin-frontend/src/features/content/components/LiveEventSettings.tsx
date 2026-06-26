import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Radio, Save } from 'lucide-react';
import { cn } from '@/lib/utils';
import { Button } from '@/components/ui/button';
import { SwitchField } from '@/components/form/SwitchField';
import { useToast } from '@/hooks/useToast';
import { SeoPanel } from './SeoPanel';
import { OgImagePicker } from './OgImagePicker';
import { useUpdateArticle } from '../hooks';
import type { ArticleData, LiveEventStatus } from '@/types/content.types';

const STATUSES: LiveEventStatus[] = ['scheduled', 'live', 'paused', 'completed'];

const STATUS_TONE: Record<LiveEventStatus, string> = {
  scheduled: 'border-sky-500 bg-sky-500/10 text-sky-600 dark:text-sky-400',
  live: 'border-destructive bg-destructive/10 text-destructive',
  paused: 'border-amber-500 bg-amber-500/10 text-amber-600 dark:text-amber-400',
  completed: 'border-emerald-500 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400',
};

const cardHead =
  'flex items-center gap-2 border-b border-border px-4 py-2.5 text-xs font-bold uppercase text-muted-foreground';

/**
 * لوحة إعدادات الحدث المباشر — حالة الحدث + الأعلام التحريرية + سيو/مشاركة
 * مخصّصة للحدث المباشر (كلها تُطبَّق على المقال المالك للتغطية).
 */
export function LiveEventSettings({ article }: { article: ArticleData }) {
  const { t } = useTranslation('content');
  const { success } = useToast();
  const update = useUpdateArticle();

  const patch = (payload: Record<string, unknown>) => update.mutate({ id: article.id, payload });

  const [seo, setSeo] = useState({
    seoTitle: article.seo.title ?? '',
    seoDescription: article.seo.description ?? '',
    seoKeywords: article.seo.keywords ?? '',
    canonicalUrl: article.seo.canonical_url ?? '',
    robots: article.seo.robots ?? '',
  });

  const saveSeo = () => {
    update.mutate(
      {
        id: article.id,
        payload: {
          seo_title: seo.seoTitle || null,
          seo_description: seo.seoDescription || null,
          seo_keywords: seo.seoKeywords || null,
          canonical_url: seo.canonicalUrl || null,
          robots: seo.robots || null,
        },
      },
      { onSuccess: () => success(t('liveCoverage.settings.seoSaved')) },
    );
  };

  const status = article.event_status ?? 'scheduled';
  const coverUrl = article.media?.cover?.url ?? article.media?.cover?.thumb ?? null;

  return (
    <div className="space-y-5">
      {/* Event status */}
      <section className="border border-border bg-background">
        <h3 className={cardHead}>
          <Radio className="h-3.5 w-3.5 text-destructive" />
          {t('liveCoverage.settings.eventStatus')}
        </h3>
        <div className="grid grid-cols-2 gap-2 p-3">
          {STATUSES.map((s) => (
            <button
              key={s}
              type="button"
              onClick={() => patch({ event_status: s })}
              disabled={update.isPending}
              className={cn(
                'border px-2 py-2 text-xs font-medium transition-colors disabled:opacity-60',
                status === s
                  ? STATUS_TONE[s]
                  : 'border-border text-muted-foreground hover:border-primary',
              )}
            >
              {t(`liveCoverage.eventStatus.${s}`)}
            </button>
          ))}
        </div>
      </section>

      {/* Editorial flags */}
      <section className="border border-border bg-background">
        <h3 className={cardHead}>{t('liveCoverage.settings.editorial')}</h3>
        <div className="space-y-2 p-3">
          <SwitchField
            label={t('articles.form.isBreaking')}
            checked={article.is_breaking}
            onChange={(v) => patch({ is_breaking: v })}
          />
          <SwitchField
            label={t('articles.form.isFeatured')}
            checked={article.is_featured}
            onChange={(v) => patch({ is_featured: v })}
          />
          <SwitchField
            label={t('articles.form.isHeader')}
            checked={article.is_header}
            onChange={(v) => patch({ is_header: v })}
          />
          <SwitchField
            label={t('articles.form.isEditorPick')}
            checked={article.is_editor_pick}
            onChange={(v) => patch({ is_editor_pick: v })}
          />
          <SwitchField
            label={t('articles.form.commentsEnabled')}
            checked={article.comments_enabled}
            onChange={(v) => patch({ comments_enabled: v })}
          />
        </div>
      </section>

      {/* Live SEO + share (dedicated to the live event) */}
      <section className="border border-border bg-background">
        <h3 className={cn(cardHead, 'justify-between')}>
          <span>{t('liveCoverage.settings.seo')}</span>
          <Button type="button" size="sm" variant="outline" onClick={saveSeo} disabled={update.isPending}>
            <Save className="h-3.5 w-3.5" />
            {t('liveCoverage.settings.saveSeo')}
          </Button>
        </h3>
        <div className="space-y-4 p-3">
          <OgImagePicker
            value={article.og_image ?? null}
            onChange={(assetId) => patch({ og_image_id: assetId })}
          />
          <SeoPanel
            title={article.title}
            excerpt={article.excerpt ?? ''}
            slug={article.slug}
            locale={article.locale}
            seoTitle={seo.seoTitle}
            seoDescription={seo.seoDescription}
            seoKeywords={seo.seoKeywords}
            canonicalUrl={seo.canonicalUrl}
            robots={seo.robots}
            coverUrl={coverUrl}
            sharePath={article.canonical_path}
            onChange={(p) =>
              setSeo((prev) => ({
                ...prev,
                ...(p.seoTitle !== undefined ? { seoTitle: p.seoTitle } : {}),
                ...(p.seoDescription !== undefined ? { seoDescription: p.seoDescription } : {}),
                ...(p.seoKeywords !== undefined ? { seoKeywords: p.seoKeywords } : {}),
                ...(p.canonicalUrl !== undefined ? { canonicalUrl: p.canonicalUrl } : {}),
                ...(p.robots !== undefined ? { robots: p.robots } : {}),
              }))
            }
          />
        </div>
      </section>
    </div>
  );
}
