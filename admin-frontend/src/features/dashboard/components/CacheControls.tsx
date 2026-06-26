import { useState } from 'react';
import { useQueryClient } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { DatabaseZap } from 'lucide-react';
import { Panel } from '@/components/analytics/AnalyticsKit';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/useToast';
import { useAuth } from '@/stores/auth.store';
import { useClearContentCache } from '@/features/system/hooks';
import type { ClearCacheResult } from '@/types/system.types';

function CacheControlsInner() {
  const { t } = useTranslation('common');
  const qc = useQueryClient();
  const { success } = useToast();
  const [last, setLast] = useState<ClearCacheResult | null>(null);
  const mutation = useClearContentCache();

  const onClear = () => {
    mutation.mutate(undefined, {
      onSuccess: (res) => {
        setLast(res);
        success(t('dashboard.cache.cleared'));
        // إنعاش بيانات اللوحة بعد التنظيف (إعادة استخدام مفاتيح queries القائمة).
        void qc.invalidateQueries({ queryKey: ['site-analytics'] });
        void qc.invalidateQueries({ queryKey: ['dashboard'] });
      },
    });
  };

  return (
    <Panel title={t('dashboard.cache.title')} icon={DatabaseZap}>
      <div className="space-y-3">
        <Button onClick={onClear} disabled={mutation.isPending} variant="outline" size="sm">
          {mutation.isPending ? t('dashboard.cache.clearing') : t('dashboard.cache.clearBtn')}
        </Button>
        {last ? (
          <div className="space-y-1 text-xs text-muted-foreground">
            <p>
              {t('dashboard.cache.lastCleared')}: {new Date(last.at).toLocaleString()}
            </p>
            <p>
              {t('dashboard.cache.groups')}: {last.cleared.join('، ')}
            </p>
          </div>
        ) : (
          <p className="text-xs text-muted-foreground">{t('dashboard.cache.noneThisSession')}</p>
        )}
      </div>
    </Panel>
  );
}

/** تحكّم الكاش — زرّ التنظيف القائم + نتيجة آخر تنظيف ضمن الجلسة فقط. يتطلّب cache.clear القائمة. */
export default function CacheControls() {
  const { hasPermission } = useAuth();
  if (!hasPermission('cache.clear')) return null;
  return <CacheControlsInner />;
}
