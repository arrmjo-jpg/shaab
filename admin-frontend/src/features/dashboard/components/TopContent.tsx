import { useTranslation } from 'react-i18next';
import { Clapperboard, FileText, Film, Newspaper, type LucideIcon } from 'lucide-react';
import { Link } from 'react-router-dom';
import { fmtNum, Panel } from '@/components/analytics/AnalyticsKit';
import { paths } from '@/router/paths';
import type { SiteAnalytics, SiteTopItem } from '@/types/analytics.types';

function TopList({
  title,
  icon,
  items,
  editPath,
}: {
  title: string;
  icon: LucideIcon;
  items: SiteTopItem[];
  editPath: (id: number) => string;
}) {
  const { t } = useTranslation('common');

  return (
    <Panel title={title} icon={icon}>
      {items.length === 0 ? (
        <p className="text-sm text-muted-foreground">{t('dashboard.top.empty')}</p>
      ) : (
        <ol className="space-y-1.5 text-sm">
          {items.map((it, idx) => (
            <li key={it.id}>
              <Link
                to={editPath(it.id)}
                className="flex items-center justify-between gap-3 transition-colors hover:text-primary"
              >
                <span className="flex min-w-0 items-center gap-2">
                  <span className="tabular-nums text-xs text-muted-foreground">{idx + 1}</span>
                  <span className="truncate">{it.title}</span>
                </span>
                <span className="shrink-0 tabular-nums text-muted-foreground">{fmtNum(it.views)}</span>
              </Link>
            </li>
          ))}
        </ol>
      )}
    </Panel>
  );
}

/** أعلى المحتوى بالوزن (نفس صيغة scoring القائمة) — قراءة-فقط، 4 لوحات صدارة. */
export default function TopContent({ top }: { top: NonNullable<SiteAnalytics['top']> }) {
  const { t } = useTranslation('common');

  return (
    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
      <TopList
        title={t('dashboard.top.articles')}
        icon={FileText}
        items={top.articles}
        editPath={(id) => paths.articlesEdit.replace(':id', String(id))}
      />
      <TopList
        title={t('dashboard.top.news')}
        icon={Newspaper}
        items={top.news}
        editPath={(id) => paths.articlesEdit.replace(':id', String(id))}
      />
      <TopList
        title={t('dashboard.top.reels')}
        icon={Clapperboard}
        items={top.reels}
        editPath={(id) => paths.reelsEdit.replace(':id', String(id))}
      />
      <TopList
        title={t('dashboard.top.videos')}
        icon={Film}
        items={top.videos}
        editPath={(id) => paths.vlVideosEdit.replace(':id', String(id))}
      />
    </div>
  );
}
