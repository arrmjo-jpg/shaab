import { useTranslation } from 'react-i18next';
import { Plus } from 'lucide-react';
import { AiActionButton } from './AiActionButton';
import { useAiTags } from '../../ai.hooks';
import type { AiEditorialContext, AiTagSuggestions } from '@/types/content.types';

interface Props {
  getContext: () => AiEditorialContext;
  current: string[];
  onAdd: (tag: string) => void;
}

type TagGroupKey = Exclude<keyof AiTagSuggestions, 'source'>;

const GROUPS: Array<{ key: TagGroupKey; labelKey: string }> = [
  { key: 'topics', labelKey: 'ai.tags.topics' },
  { key: 'people', labelKey: 'ai.tags.people' },
  { key: 'locations', labelKey: 'ai.tags.locations' },
  { key: 'organizations', labelKey: 'ai.tags.organizations' },
];

/** زر «اقتراح ذكي» للوسوم — رقائق قابلة للنقر فقط، بلا تطبيق تلقائي. */
export function TagSuggest({ getContext, current, onAdd }: Props) {
  const { t } = useTranslation('content');
  const mut = useAiTags();

  const has = (tag: string) => current.some((c) => c.toLowerCase() === tag.toLowerCase());

  return (
    <div className="space-y-2">
      <AiActionButton
        label={t('ai.tags.button')}
        onClick={() => mut.mutate(getContext())}
        loading={mut.isPending}
      />

      {mut.data ? (
        <div className="space-y-2 border border-primary/30 bg-primary/5 p-2.5">
          {GROUPS.map(({ key, labelKey }) => {
            const fresh = mut.data![key].filter((tag) => !has(tag));
            if (fresh.length === 0) return null;
            return (
              <div key={key} className="space-y-1">
                <p className="text-[11px] font-bold text-muted-foreground">{t(labelKey)}</p>
                <div className="flex flex-wrap gap-1.5">
                  {fresh.map((tag) => (
                    <button
                      key={tag}
                      type="button"
                      onClick={() => onAdd(tag)}
                      className="inline-flex items-center gap-1 border border-border bg-background px-2 py-0.5 text-xs transition-colors hover:border-primary hover:bg-primary/10"
                    >
                      <Plus className="h-3 w-3" />
                      {tag}
                    </button>
                  ))}
                </div>
              </div>
            );
          })}
          {mut.data.source === 'auto' ? (
            <p className="text-[11px] text-muted-foreground">{t('ai.autoNote')}</p>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
