import { useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import { ArrowRight, RefreshCw, AlertTriangle, CheckCircle2, ShieldCheck, Rocket } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useGeneratePreview, useApproveRun } from '../hooks';
import type { ConflictPolicy, MigrationRun } from '@/types/wpMigration.types';

const fmt = (n: number): string => new Intl.NumberFormat('en-US').format(n);
const POLICIES: ConflictPolicy[] = ['prefer_news', 'prefer_articles', 'exclude'];

const POLICY_KEY: Record<ConflictPolicy, string> = {
  prefer_news: 'PreferNews',
  prefer_articles: 'PreferArticles',
  exclude: 'Exclude',
};

function Card({ label, value, tone }: { label: string; value: ReactNode; tone?: string }) {
  return (
    <div className={`border border-border bg-background p-4 ${tone ?? ''}`}>
      <div className="text-2xl font-bold tabular-nums">{value}</div>
      <div className="mt-1 text-xs text-muted-foreground">{label}</div>
    </div>
  );
}

export function ImpactPreviewStep({
  run,
  onBack,
  onExecution,
}: {
  run: MigrationRun;
  onBack: () => void;
  onExecution?: () => void;
}) {
  const { t } = useTranslation('wpMigration');
  const generate = useGeneratePreview(run.id);
  const approve = useApproveRun(run.id);
  const [policy, setPolicy] = useState<ConflictPolicy>(run.conflict_policy ?? 'prefer_news');

  const preview = run.preview;
  const stale = run.preview_stale;
  const conflicts = preview?.totals.conflicts ?? 0;

  const typeLabel = (type: 'news' | 'opinion' | 'conflict'): string =>
    type === 'opinion' ? t('mapping.modeArticles') : t('mapping.modeNews');

  return (
    <div className="space-y-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h2 className="text-xl font-bold">{t('preview.title')}</h2>
          <p className="text-sm text-muted-foreground">{t('preview.subtitle')}</p>
        </div>
        <div className="flex items-center gap-2">
          <Button variant="outline" onClick={onBack}>
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
            {t('preview.back')}
          </Button>
          <Button variant="outline" onClick={() => generate.mutate()} disabled={generate.isPending}>
            <RefreshCw className="h-4 w-4" />
            {generate.isPending
              ? t('preview.generating')
              : preview
                ? t('preview.regenerate')
                : t('preview.generate')}
          </Button>
          {preview && !stale ? (
            <Button onClick={() => approve.mutate(policy)} disabled={approve.isPending} variant="outline">
              <ShieldCheck className="h-4 w-4" />
              {approve.isPending ? t('preview.approving') : t('preview.approve')}
            </Button>
          ) : null}
          {onExecution && run.approved && !stale ? (
            <Button onClick={onExecution}>
              <Rocket className="h-4 w-4" />
              {t('exec.open')}
            </Button>
          ) : null}
        </div>
      </div>

      {run.approved && !stale ? (
        <div className="flex items-center gap-2 border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-medium text-emerald-600">
          <CheckCircle2 className="h-4 w-4" />
          {t('preview.approvedBadge')}
        </div>
      ) : null}

      {stale ? (
        <div className="flex items-center gap-2 border border-amber-500/40 bg-amber-500/10 px-4 py-3 text-sm font-medium text-amber-600">
          <AlertTriangle className="h-4 w-4" />
          {t('preview.staleBanner')}
        </div>
      ) : null}

      {!preview ? (
        <div className="border border-border bg-background p-8 text-center">
          <p className="mb-4 text-sm text-muted-foreground">{t('preview.notGenerated')}</p>
          <Button onClick={() => generate.mutate()} disabled={generate.isPending}>
            <RefreshCw className="h-4 w-4" />
            {generate.isPending ? t('preview.generating') : t('preview.generate')}
          </Button>
        </div>
      ) : (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Card label={t('preview.cardUnique')} value={fmt(preview.totals.unique_posts)} />
            <Card label={t('preview.cardNews')} value={fmt(preview.totals.news)} />
            <Card label={t('preview.cardArticles')} value={fmt(preview.totals.articles)} />
            <Card
              label={t('preview.cardConflicts')}
              value={fmt(preview.totals.conflicts)}
              tone={conflicts > 0 ? 'border-amber-500/50 bg-amber-500/5' : undefined}
            />
            <Card label={t('preview.cardFeatured')} value={fmt(preview.media.featured_unique)} />
            <Card label={t('preview.cardInline')} value={fmt(preview.media.posts_with_inline)} />
            <Card label={t('preview.cardSeo')} value={fmt(preview.seo.mapped)} />
            <Card label={t('preview.cardRedirects')} value={fmt(preview.redirects.estimated)} />
          </div>

          {conflicts > 0 ? (
            <section className="border border-amber-500/50 bg-amber-500/5 p-5">
              <div className="mb-2 flex items-center gap-2 text-amber-600">
                <AlertTriangle className="h-5 w-5" />
                <h3 className="text-sm font-bold">{t('preview.conflictTitle')}</h3>
              </div>
              <p className="mb-4 text-sm">{t('preview.conflictDesc', { n: fmt(conflicts) })}</p>
              <div className="grid gap-3 sm:grid-cols-3">
                {POLICIES.map((p) => (
                  <label
                    key={p}
                    className={`cursor-pointer border p-3 text-sm transition-colors ${
                      policy === p ? 'border-primary bg-primary/5' : 'border-border hover:bg-accent/40'
                    }`}
                  >
                    <span className="flex items-center gap-2 font-semibold">
                      <input
                        type="radio"
                        name="conflict-policy"
                        className="accent-primary"
                        checked={policy === p}
                        onChange={() => setPolicy(p)}
                      />
                      {t(`preview.policy${POLICY_KEY[p]}`)}
                    </span>
                    <span className="mt-1 block text-xs text-muted-foreground">
                      {t(`preview.policy${POLICY_KEY[p]}Desc`)}
                    </span>
                  </label>
                ))}
              </div>
            </section>
          ) : null}

          <section className="space-y-3">
            <h3 className="text-sm font-bold">{t('preview.samplesTitle')}</h3>
            <div className="grid gap-3 lg:grid-cols-2">
              {preview.samples.map((s) => (
                <div key={s.source.id} className="border border-border bg-background p-4">
                  <div className="grid gap-3 sm:grid-cols-2">
                    <div>
                      <div className="text-xs font-semibold text-muted-foreground">
                        {t('preview.sampleSource')}
                      </div>
                      <div className="mt-1 font-bold">{s.source.title}</div>
                      <p className="mt-1 text-xs text-muted-foreground">{s.source.excerpt}</p>
                    </div>
                    <div className="border-t border-border pt-3 sm:border-s sm:border-t-0 sm:ps-3 sm:pt-0">
                      <div className="flex items-center gap-2 text-xs font-semibold text-muted-foreground">
                        {t('preview.sampleTarget')}
                        {s.target.is_conflict ? (
                          <span className="bg-amber-500/15 px-1.5 py-0.5 text-[10px] font-bold text-amber-600">
                            {t('preview.sampleConflictBadge')}
                          </span>
                        ) : (
                          <span className="bg-primary/10 px-1.5 py-0.5 text-[10px] font-bold text-primary">
                            {typeLabel(s.target.type)}
                          </span>
                        )}
                      </div>
                      <div className="mt-1 font-bold">{s.target.seo_title || s.source.title}</div>
                      <p className="mt-1 text-xs text-muted-foreground">
                        {s.target.seo_description || t('preview.sampleNoSeo')}
                      </p>
                      {s.target.target_categories.length > 0 ? (
                        <div className="mt-2 text-xs text-muted-foreground">
                          {s.target.target_categories.join('، ')}
                        </div>
                      ) : null}
                      <div className="mt-1 text-xs text-muted-foreground">
                        {t('preview.sampleByline')}: {s.target.byline}
                      </div>
                    </div>
                  </div>
                </div>
              ))}
            </div>
          </section>
        </>
      )}
    </div>
  );
}
