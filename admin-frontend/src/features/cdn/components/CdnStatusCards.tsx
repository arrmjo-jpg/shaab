import { useTranslation } from 'react-i18next';
import {
  CheckCircle2,
  XCircle,
  Power,
  ShieldCheck,
  Eraser,
  AlertTriangle,
  Link2,
  Gauge as GaugeIcon,
} from 'lucide-react';
import type { CdnStatus } from '@/types/cdn.types';
import { Gauge } from './Gauge';
import { Donut } from './Donut';
import { StatCard } from './StatCard';

function Chip({ ok, label }: { ok: boolean; label: string }) {
  return (
    <div className="flex items-center justify-between border border-border bg-background px-4 py-3">
      <span className="text-sm text-muted-foreground">{label}</span>
      {ok ? (
        <CheckCircle2 className="h-5 w-5 text-emerald-500" />
      ) : (
        <XCircle className="h-5 w-5 text-muted-foreground" />
      )}
    </div>
  );
}

export function CdnStatusCards({ status }: { status: CdnStatus }) {
  const { t } = useTranslation('cdn');
  const s = status.stats;

  // درجة قوّة النظام: تشغيل + اكتمال إعداد + آخر اختبار اتصال
  const health =
    (status.enabled ? 40 : 0) +
    (status.configured ? 40 : 0) +
    (s.last_test_ok === true ? 20 : s.last_test_ok === false ? 0 : 10);

  const fmt = (v: string | null) => (v ? new Date(v).toLocaleString() : t('status.never'));

  return (
    <section className="space-y-4">
      {/* لوحة القوّة الرئيسية */}
      <div className="grid gap-4 lg:grid-cols-3">
        <div className="flex items-center justify-center border border-border bg-background p-6">
          <Gauge value={health} label={t('overview.health')} caption={t('overview.healthCaption')} />
        </div>

        <div className="flex items-center justify-center border border-border bg-background p-6">
          <Donut
            success={s.purge_success}
            failed={s.purge_failed}
            label={t('overview.successRate')}
            successLabel={t('status.purgeSuccess')}
            failedLabel={t('status.purgeFailed')}
          />
        </div>

        <div className="grid gap-4">
          <Chip ok={status.enabled} label={t('status.enabled')} />
          <Chip ok={status.configured} label={t('status.configured')} />
          <Chip ok={status.auto_purge} label={t('status.autoPurge')} />
          <div className="flex items-center justify-between border border-border bg-background px-4 py-3">
            <span className="text-sm text-muted-foreground">{t('status.plan')}</span>
            <span className="flex items-center gap-1.5 text-sm font-semibold text-primary">
              <GaugeIcon className="h-4 w-4" />
              {t(`plans.${status.plan}`, status.plan)}
            </span>
          </div>
        </div>
      </div>

      {/* بطاقات الإحصاء القوية */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard icon={ShieldCheck} accent="emerald" label={t('status.purgeSuccess')} value={s.purge_success} />
        <StatCard icon={AlertTriangle} accent="destructive" label={t('status.purgeFailed')} value={s.purge_failed} />
        <StatCard icon={Link2} accent="primary" label={t('status.purgedUrls')} value={s.purged_urls} />
        <StatCard
          icon={s.last_test_ok ? CheckCircle2 : Power}
          accent={s.last_test_ok ? 'emerald' : 'primary'}
          label={t('status.lastTest')}
          value={fmt(s.last_test_at)}
        />
      </div>

      <div className="flex items-center gap-3 border border-border bg-background px-5 py-4 text-sm">
        <Eraser className="h-5 w-5 text-muted-foreground" />
        <span className="text-muted-foreground">{t('status.lastPurge')}:</span>
        <span className="font-semibold">{fmt(s.last_purge_at)}</span>
      </div>
    </section>
  );
}
