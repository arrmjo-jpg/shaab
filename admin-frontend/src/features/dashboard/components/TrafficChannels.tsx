import { useTranslation } from 'react-i18next';
import { Radio } from 'lucide-react';
import { BarRow, Panel } from '@/components/analytics/AnalyticsKit';
import type { SiteAnalytics } from '@/types/analytics.types';

const ROWS: Array<{ key: keyof NonNullable<SiteAnalytics['channels']>; color: string }> = [
  { key: 'direct', color: 'bg-sky-500' },
  { key: 'internal', color: 'bg-emerald-500' },
  { key: 'search', color: 'bg-amber-500' },
  { key: 'social', color: 'bg-violet-500' },
  { key: 'referral', color: 'bg-rose-500' },
];

/**
 * تقسيم مصادر المرور الخمسة (content_daily_stats §A) — يعيد استخدام BarRow القائم.
 * عدّادات قنوات مجمَّعة فقط؛ لا نطاقات/URLs (تلك §B.4 مؤجَّلة).
 */
export default function TrafficChannels({
  channels,
}: {
  channels: NonNullable<SiteAnalytics['channels']>;
}) {
  const { t } = useTranslation('common');
  const total = ROWS.reduce((sum, r) => sum + channels[r.key], 0);

  return (
    <Panel title={t('dashboard.channels.title')} icon={Radio}>
      {total === 0 ? (
        <p className="text-sm text-muted-foreground">{t('dashboard.top.empty')}</p>
      ) : (
        <div className="space-y-3">
          {ROWS.map((r) => (
            <BarRow
              key={r.key}
              label={t(`dashboard.channels.${r.key}`)}
              value={channels[r.key]}
              total={total}
              color={r.color}
            />
          ))}
        </div>
      )}
    </Panel>
  );
}
