import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Calendar, CheckCircle2, RotateCcw, Send, Archive, XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/useToast';
import { useTransitionArticle } from '../hooks';
import { allowedTransitions } from '../lib/workflow';
import type { ArticleData, ArticleStatus } from '@/types/content.types';

interface Props {
  article: ArticleData;
  isEditorial: boolean;
  isOwner: boolean;
}

const ICONS: Record<ArticleStatus, typeof Send> = {
  draft: RotateCcw,
  submitted: Send,
  in_review: Send,
  scheduled: Calendar,
  published: CheckCircle2,
  rejected: XCircle,
  archived: Archive,
};

const TONES: Record<ArticleStatus, 'default' | 'success' | 'muted' | 'destructive'> = {
  draft: 'muted',
  submitted: 'default',
  in_review: 'default',
  scheduled: 'default',
  published: 'success',
  rejected: 'destructive',
  archived: 'muted',
};

export function ArticleWorkflowPanel({ article, isEditorial, isOwner }: Props) {
  const { t, i18n } = useTranslation('content');
  const { confirm } = useToast();
  const mutate = useTransitionArticle();

  const [scheduledAt, setScheduledAt] = useState<string>('');

  const transitions = allowedTransitions({
    isEditorial,
    isOwner,
    currentStatus: article.status,
  });

  const fmtDate = (v: string | null) =>
    v
      ? new Intl.DateTimeFormat(i18n.language, {
          dateStyle: 'medium',
          timeStyle: 'short',
        }).format(new Date(v))
      : '—';

  const onTrigger = async (target: ArticleStatus) => {
    if (target === 'scheduled' && !scheduledAt) return;

    const confirmed = await confirm({
      title: t(`articles.workflow.confirm.${target}.title`),
      text: t(`articles.workflow.confirm.${target}.text`),
      confirmText: t('articles.workflow.confirm.yes'),
      cancelText: t('common.cancel', { ns: 'common' }),
    });
    if (!confirmed) return;

    mutate.mutate({
      id: article.id,
      status: target,
      scheduledAt: target === 'scheduled' ? new Date(scheduledAt).toISOString() : null,
    });
  };

  return (
    <section className="border border-border bg-background p-5">
      <div className="mb-5 space-y-1">
        <h3 className="text-sm font-bold">{t('articles.workflow.title')}</h3>
        <p className="text-xs text-muted-foreground">{t('articles.workflow.hint')}</p>
      </div>

      <div className="mb-5 flex flex-wrap items-center gap-3 border border-border bg-muted/30 p-3">
        <span className="text-xs uppercase text-muted-foreground">
          {t('articles.workflow.currentStatus')}
        </span>
        <Badge variant={TONES[article.status]}>
          {t(`articles.status.${article.status}`)}
        </Badge>
        {article.published_at ? (
          <span className="text-xs text-muted-foreground">
            {t('articles.workflow.publishedAt')}: {fmtDate(article.published_at)}
          </span>
        ) : null}
      </div>

      {transitions.length === 0 ? (
        <p className="text-sm text-muted-foreground">
          {t('articles.workflow.noActions')}
        </p>
      ) : (
        <div className="space-y-3">
          {transitions.includes('scheduled') ? (
            <div className="flex flex-wrap items-center gap-2">
              <label className="text-xs text-muted-foreground" htmlFor="wf-scheduled-at">
                {t('articles.workflow.scheduledAtLabel')}
              </label>
              <input
                id="wf-scheduled-at"
                type="datetime-local"
                value={scheduledAt}
                onChange={(e) => setScheduledAt(e.target.value)}
                className="h-9 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              />
            </div>
          ) : null}

          <div className="flex flex-wrap gap-2">
            {transitions.map((target) => {
              const Icon = ICONS[target];
              const disabled =
                mutate.isPending || (target === 'scheduled' && !scheduledAt);
              return (
                <Button
                  key={target}
                  type="button"
                  variant={target === 'rejected' || target === 'archived' ? 'outline' : 'default'}
                  size="sm"
                  disabled={disabled}
                  onClick={() => onTrigger(target)}
                >
                  <Icon className="h-4 w-4" />
                  {t(`articles.workflow.action.${target}`)}
                </Button>
              );
            })}
          </div>
        </div>
      )}
    </section>
  );
}
