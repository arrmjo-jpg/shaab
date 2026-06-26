import { useTranslation } from 'react-i18next';
import { BarChart3, Eye, Heart, ThumbsDown, ThumbsUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { EngagementMetrics } from '@/types/content.types';

interface Props {
  metrics?: EngagementMetrics;
  locale: string;
}

/**
 * أيقونة تحليلات واحدة → لوحة مقاييس مُدمَجة (مشاهدات/إعجابات/عدم إعجاب/حفظ).
 * موحّدة لكل أنواع المحتوى — تعتمد عقد المقاييس العام.
 */
export function EngagementMetricsButton({ metrics, locale }: Props) {
  const { t } = useTranslation('content');
  const m = metrics ?? { views: 0, likes: 0, dislikes: 0, favorites: 0 };

  const rows = [
    { icon: Eye, label: t('engagement.views'), value: m.views, tone: 'text-primary' },
    { icon: ThumbsUp, label: t('engagement.likes'), value: m.likes, tone: 'text-emerald-600 dark:text-emerald-400' },
    { icon: ThumbsDown, label: t('engagement.dislikes'), value: m.dislikes, tone: 'text-destructive' },
    { icon: Heart, label: t('engagement.favorites'), value: m.favorites, tone: 'text-rose-600 dark:text-rose-400' },
  ];

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" className="h-8 w-8" title={t('engagement.title')}>
          <BarChart3 className="h-4 w-4" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-52 p-2">
        <p className="px-1 pb-1.5 text-[11px] font-bold uppercase text-muted-foreground">
          {t('engagement.title')}
        </p>
        <div className="space-y-0.5">
          {rows.map((r) => {
            const Icon = r.icon;
            return (
              <div
                key={r.label}
                className="flex items-center justify-between gap-3 px-1 py-1 text-sm"
              >
                <span className="flex items-center gap-2 text-muted-foreground">
                  <Icon className={`h-4 w-4 ${r.tone}`} />
                  {r.label}
                </span>
                <span className="font-semibold tabular-nums">{r.value.toLocaleString(locale)}</span>
              </div>
            );
          })}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
