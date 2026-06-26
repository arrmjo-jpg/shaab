import { useTranslation } from 'react-i18next';
import { AlertCircle, CheckCircle2, Lightbulb } from 'lucide-react';
import { cn } from '@/lib/utils';
import { AiActionButton } from './AiActionButton';
import { useAiSeo } from '../../ai.hooks';
import type { ContentLocale } from '@/types/content.types';

interface Props {
  getPayload: () => {
    title?: string;
    excerpt?: string;
    slug?: string;
    tags?: string[];
    locale?: ContentLocale;
  };
}

function scoreTone(score: number): string {
  if (score >= 75) return 'text-emerald-600 dark:text-emerald-400';
  if (score >= 45) return 'text-amber-600 dark:text-amber-400';
  return 'text-destructive';
}

/** بطاقة تحليل سيو استشاري — نتيجة + ملاحظات + كلمات ناقصة + اقتراحات. */
export function SeoAnalysisCard({ getPayload }: Props) {
  const { t } = useTranslation('content');
  const mut = useAiSeo();
  const data = mut.data;

  return (
    <div className="mt-4 space-y-3 border border-primary/30 bg-primary/5 p-3">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs font-bold text-foreground">{t('ai.seo.title')}</p>
        <AiActionButton
          label={t('ai.seo.button')}
          onClick={() => mut.mutate(getPayload())}
          loading={mut.isPending}
        />
      </div>

      {data ? (
        <div className="space-y-3">
          <div className="flex items-center gap-2">
            <span className={cn('text-2xl font-bold', scoreTone(data.score))}>{data.score}</span>
            <span className="text-xs text-muted-foreground">/ 100</span>
            {data.source === 'auto' ? (
              <span className="ms-auto text-[11px] text-muted-foreground">{t('ai.autoNote')}</span>
            ) : null}
          </div>

          {data.title_feedback ? (
            <p className="flex items-start gap-1.5 text-xs">
              <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
              <span>{data.title_feedback}</span>
            </p>
          ) : null}
          {data.description_feedback ? (
            <p className="flex items-start gap-1.5 text-xs">
              <CheckCircle2 className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
              <span>{data.description_feedback}</span>
            </p>
          ) : null}

          {data.missing_keywords.length > 0 ? (
            <div className="space-y-1">
              <p className="flex items-center gap-1.5 text-[11px] font-bold text-muted-foreground">
                <AlertCircle className="h-3.5 w-3.5" />
                {t('ai.seo.missingKeywords')}
              </p>
              <div className="flex flex-wrap gap-1.5">
                {data.missing_keywords.map((k) => (
                  <span key={k} className="border border-border bg-background px-2 py-0.5 text-xs">
                    {k}
                  </span>
                ))}
              </div>
            </div>
          ) : null}

          {data.suggestions.length > 0 ? (
            <ul className="space-y-1">
              {data.suggestions.map((s, i) => (
                <li key={i} className="flex items-start gap-1.5 text-xs text-muted-foreground">
                  <Lightbulb className="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-500" />
                  <span>{s}</span>
                </li>
              ))}
            </ul>
          ) : null}
        </div>
      ) : (
        <p className="text-xs text-muted-foreground">{t('ai.seo.hint')}</p>
      )}
    </div>
  );
}
