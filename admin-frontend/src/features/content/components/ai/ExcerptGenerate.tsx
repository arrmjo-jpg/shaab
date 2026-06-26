import { useTranslation } from 'react-i18next';
import { Check, X } from 'lucide-react';
import { AiActionButton } from './AiActionButton';
import { useAiExcerpt } from '../../ai.hooks';
import type { AiEditorialContext } from '@/types/content.types';

interface Props {
  getContext: () => AiEditorialContext;
  onApply: (excerpt: string) => void;
}

/** زر «توليد ملخص» + معاينة قابلة للمراجعة قبل التطبيق — لا حفظ تلقائي. */
export function ExcerptGenerate({ getContext, onApply }: Props) {
  const { t } = useTranslation('content');
  const mut = useAiExcerpt();

  return (
    <div className="space-y-2">
      <AiActionButton
        label={t('ai.excerpt.button')}
        onClick={() => mut.mutate(getContext())}
        loading={mut.isPending}
      />

      {mut.data ? (
        <div className="space-y-2 border border-primary/30 bg-primary/5 p-2.5">
          <p className="text-xs leading-relaxed text-foreground">{mut.data.excerpt}</p>
          {mut.data.source === 'auto' ? (
            <p className="text-[11px] text-muted-foreground">{t('ai.autoNote')}</p>
          ) : null}
          <div className="flex items-center gap-2">
            <button
              type="button"
              onClick={() => {
                onApply(mut.data!.excerpt);
                mut.reset();
              }}
              className="inline-flex items-center gap-1 bg-primary px-2 py-1 text-xs font-medium text-primary-foreground"
            >
              <Check className="h-3.5 w-3.5" />
              {t('ai.apply')}
            </button>
            <button
              type="button"
              onClick={() => mut.mutate(getContext())}
              className="text-xs text-muted-foreground hover:text-foreground"
            >
              {t('ai.regenerate')}
            </button>
            <button
              type="button"
              onClick={() => mut.reset()}
              className="ms-auto inline-flex items-center text-muted-foreground hover:text-destructive"
              title={t('ai.dismiss')}
            >
              <X className="h-3.5 w-3.5" />
            </button>
          </div>
        </div>
      ) : null}
    </div>
  );
}
