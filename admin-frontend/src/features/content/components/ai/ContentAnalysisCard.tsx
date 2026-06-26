import { useTranslation } from 'react-i18next';
import { AlertTriangle, BookOpen, Lightbulb } from 'lucide-react';
import { cn } from '@/lib/utils';
import { AiActionButton } from './AiActionButton';
import { useAiAnalyze } from '../../ai.hooks';
import type { AiEditorialContext } from '@/types/content.types';

interface Props {
  getContext: () => AiEditorialContext;
}

function scoreTone(score: number): string {
  if (score >= 75) return 'text-emerald-600 dark:text-emerald-400';
  if (score >= 45) return 'text-amber-600 dark:text-amber-400';
  return 'text-destructive';
}

/**
 * بطاقة تحليل جودة المحتوى (اقتراحات استشارية فقط — لا تعدّل النصّ).
 * ميزة ذكاء اصطناعي اختيارية: عند تعطّل المزوّد تظهر رسالة لطيفة دون تجميد.
 */
export function ContentAnalysisCard({ getContext }: Props) {
  const { t } = useTranslation('content');
  const mut = useAiAnalyze();
  const data = mut.data;

  return (
    <div className="mt-3 space-y-3 border border-primary/30 bg-primary/5 p-3">
      <div className="flex items-center justify-between gap-2">
        <p className="text-xs font-bold text-foreground">{t('ai.analyze.title')}</p>
        <AiActionButton
          label={t('ai.analyze.button')}
          onClick={() => mut.mutate(getContext())}
          loading={mut.isPending}
        />
      </div>

      {data ? (
        <div className="space-y-3">
          <div className="flex items-center gap-2">
            <span className={cn('text-2xl font-bold', scoreTone(data.score))}>{data.score}</span>
            <span className="text-xs text-muted-foreground">/ 100</span>
          </div>

          {data.readability ? (
            <p className="flex items-start gap-1.5 text-xs">
              <BookOpen className="mt-0.5 h-3.5 w-3.5 shrink-0 text-muted-foreground" />
              <span>{data.readability}</span>
            </p>
          ) : null}

          {data.issues.length > 0 ? (
            <div className="space-y-1">
              <p className="text-[11px] font-bold text-muted-foreground">{t('ai.analyze.issues')}</p>
              <ul className="space-y-1">
                {data.issues.map((issue, i) => (
                  <li key={i} className="flex items-start gap-1.5 text-xs text-foreground">
                    <AlertTriangle className="mt-0.5 h-3.5 w-3.5 shrink-0 text-amber-500" />
                    <span>{issue}</span>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}

          {data.suggestions.length > 0 ? (
            <div className="space-y-1">
              <p className="text-[11px] font-bold text-muted-foreground">
                {t('ai.analyze.suggestions')}
              </p>
              <ul className="space-y-1">
                {data.suggestions.map((s, i) => (
                  <li key={i} className="flex items-start gap-1.5 text-xs text-muted-foreground">
                    <Lightbulb className="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" />
                    <span>{s}</span>
                  </li>
                ))}
              </ul>
            </div>
          ) : null}
        </div>
      ) : (
        <p className="text-xs text-muted-foreground">{t('ai.analyze.hint')}</p>
      )}
    </div>
  );
}
