import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AiActionButton } from './AiActionButton';
import { useAiHeadlines } from '../../ai.hooks';
import type { AiEditorialContext, AiHeadlineSuggestions } from '@/types/content.types';

interface Props {
  getContext: () => AiEditorialContext;
  onApply: (title: string) => void;
}

const GROUPS: Array<{ key: keyof AiHeadlineSuggestions; labelKey: string }> = [
  { key: 'news', labelKey: 'ai.headlines.news' },
  { key: 'editorial', labelKey: 'ai.headlines.editorial' },
  { key: 'seo', labelKey: 'ai.headlines.seo' },
];

/** زر «اقتراح عنوان» + لوحة منسدلة بالاقتراحات المجمّعة — لا يستبدل العنوان تلقائياً. */
export function HeadlineSuggestions({ getContext, onApply }: Props) {
  const { t } = useTranslation('content');
  const [open, setOpen] = useState(false);
  const mut = useAiHeadlines();

  const run = () => {
    setOpen(true);
    mut.mutate(getContext());
  };

  const data = mut.data;

  return (
    <div className="relative inline-block">
      <AiActionButton label={t('ai.headlines.button')} onClick={run} loading={mut.isPending} />

      {open && (data || mut.isPending) ? (
        <>
          <div className="fixed inset-0 z-40" onClick={() => setOpen(false)} aria-hidden />
          <div className="absolute z-50 mt-1 max-h-80 w-80 overflow-y-auto border border-border bg-background p-3 shadow-soft-lg end-0">
            {mut.isPending ? (
              <p className="py-4 text-center text-xs text-muted-foreground">
                {t('ai.loading')}
              </p>
            ) : data ? (
              <div className="space-y-3">
                {GROUPS.map(({ key, labelKey }) =>
                  data[key].length > 0 ? (
                    <div key={key} className="space-y-1">
                      <p className="text-[11px] font-bold text-muted-foreground">{t(labelKey)}</p>
                      {data[key].map((headline, i) => (
                        <button
                          key={`${key}-${i}`}
                          type="button"
                          onClick={() => {
                            onApply(headline);
                            setOpen(false);
                          }}
                          className="block w-full border border-border bg-background px-2 py-1.5 text-start text-xs transition-colors hover:border-primary hover:bg-primary/5"
                        >
                          {headline}
                        </button>
                      ))}
                    </div>
                  ) : null,
                )}
              </div>
            ) : null}
          </div>
        </>
      ) : null}
    </div>
  );
}
