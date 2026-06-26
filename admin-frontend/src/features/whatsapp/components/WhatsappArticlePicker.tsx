import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Search } from 'lucide-react';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { useArticles } from '@/features/content/hooks';
import type { ArticleData } from '@/types/content.types';

interface Props {
  selected: { id: number; title: string } | null;
  onSelect: (article: { id: number; title: string } | null) => void;
}

/** منتقي خبر مبسّط — بحث في المقالات المنشورة واختيار واحد (لحملة «إرسال خبر»). */
export function WhatsappArticlePicker({ selected, onSelect }: Props) {
  const { t } = useTranslation('whatsapp');
  const [search, setSearch] = useState('');
  const [active, setActive] = useState(false);

  const q = useArticles({
    page: 1,
    per_page: 10,
    search,
    status: 'published',
    type: '',
    locale: '',
    category: '',
    placement: '',
    sort: '-published_at',
  });

  if (selected) {
    return (
      <div className="flex items-center justify-between gap-2 border border-border bg-muted/40 px-3 py-2 text-sm">
        <span className="truncate font-medium">{selected.title}</span>
        <Button variant="ghost" size="sm" onClick={() => onSelect(null)}>
          {t('campaigns.form.changeArticle')}
        </Button>
      </div>
    );
  }

  const rows = q.data?.data ?? [];

  return (
    <div className="space-y-2">
      <div className="flex gap-2">
        <Input
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          onFocus={() => setActive(true)}
          placeholder={t('campaigns.form.articleSearch')}
        />
        <Button variant="outline" size="icon" aria-label={t('campaigns.form.articleSearch')} onClick={() => setActive(true)}>
          <Search className="h-4 w-4" />
        </Button>
      </div>
      {active ? (
        <div className="max-h-56 overflow-y-auto border border-border">
          {rows.map((a: ArticleData) => (
            <button
              key={a.id}
              className="block w-full border-b border-border px-3 py-2 text-start text-sm last:border-0 hover:bg-accent"
              onClick={() => {
                onSelect({ id: a.id, title: a.title });
                setActive(false);
              }}
            >
              {a.title}
            </button>
          ))}
          {rows.length === 0 && !q.isLoading ? (
            <p className="px-3 py-2 text-sm text-muted-foreground">{t('campaigns.form.noArticles')}</p>
          ) : null}
        </div>
      ) : null}
    </div>
  );
}
