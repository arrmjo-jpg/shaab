import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { DataTable, type Column } from '@/components/data/DataTable';
import { ErrorState } from '@/components/feedback';
import { SelectField } from '@/components/form/SelectField';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { paths } from '@/router/paths';
import { useDeleteWhatsappCampaign, useWhatsappCampaigns } from '../hooks';
import { WhatsappStatusBadge } from '../components/WhatsappStatusBadge';
import type { WhatsappCampaignData, WhatsappCampaignsListParams } from '@/types/whatsapp.types';

const DEFAULT_PARAMS: WhatsappCampaignsListParams = { page: 1, per_page: 15, type: '', status: '' };

/** سجلّ حملات واتساب — قائمة بكل ما طلبه: الاسم/النوع/الحالة/المستلمون/الناجح/الفاشل/التوقيت. */
export default function WhatsappCampaignsPage() {
  const { t } = useTranslation('whatsapp');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canSend = hasPermission('whatsapp.send');

  const [params, setParams] = useState<WhatsappCampaignsListParams>(DEFAULT_PARAMS);
  const q = useWhatsappCampaigns(params);
  const del = useDeleteWhatsappCampaign();

  const rows = q.data?.data ?? [];

  const onDelete = async (c: WhatsappCampaignData) => {
    if (
      await confirm({
        title: t('campaigns.confirm.deleteTitle'),
        text: t('campaigns.confirm.deleteText', { name: c.name }),
        confirmText: t('campaigns.confirm.yes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(c.id);
  };

  const columns: Column<WhatsappCampaignData>[] = [
    {
      key: 'name',
      header: t('campaigns.col.name'),
      render: (c) => (
        <button className="text-start font-medium hover:underline" onClick={() => navigate(paths.whatsappCampaignDetail.replace(':id', String(c.id)))}>
          {c.name}
        </button>
      ),
    },
    {
      key: 'type',
      header: t('campaigns.col.type'),
      render: (c) => <Badge variant="muted">{t(`campaigns.type.${c.type}`)}</Badge>,
    },
    { key: 'status', header: t('campaigns.col.status'), render: (c) => <WhatsappStatusBadge status={c.status} /> },
    { key: 'recipients', header: t('campaigns.col.recipients'), align: 'center', render: (c) => c.recipients_total },
    { key: 'sent', header: t('campaigns.col.sent'), align: 'center', render: (c) => <span className="text-emerald-600">{c.sent_count}</span> },
    { key: 'failed', header: t('campaigns.col.failed'), align: 'center', render: (c) => <span className="text-destructive">{c.failed_count}</span> },
    {
      key: 'actions',
      header: '',
      align: 'end',
      render: (c) =>
        canSend && c.status !== 'sending' ? (
          <Button variant="ghost" size="icon" aria-label={t('campaigns.delete')} onClick={() => void onDelete(c)}>
            <Trash2 className="h-4 w-4 text-destructive" />
          </Button>
        ) : null,
    },
  ];

  if (q.isError) return <ErrorState onRetry={() => void q.refetch()} />;

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('campaigns.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('campaigns.subtitle')}</p>
        </div>
        {canSend ? (
          <Button onClick={() => navigate(paths.whatsappCampaignCreate)}>
            <Plus className="h-4 w-4" />
            {t('campaigns.create')}
          </Button>
        ) : null}
      </header>

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <SelectField
          label=""
          aria-label={t('campaigns.filterType')}
          value={params.type}
          onChange={(e) => setParams((p) => ({ ...p, type: e.target.value as typeof p.type, page: 1 }))}
          options={[
            { value: '', label: t('campaigns.allTypes') },
            { value: 'promo', label: t('campaigns.type.promo') },
            { value: 'article', label: t('campaigns.type.article') },
          ]}
        />
        <SelectField
          label=""
          aria-label={t('campaigns.filterStatus')}
          value={params.status}
          onChange={(e) => setParams((p) => ({ ...p, status: e.target.value as typeof p.status, page: 1 }))}
          options={[
            { value: '', label: t('campaigns.allStatuses') },
            ...(['draft', 'scheduled', 'sending', 'completed', 'failed', 'cancelled'] as const).map((s) => ({
              value: s,
              label: t(`campaigns.status.${s}`),
            })),
          ]}
        />
      </div>

      <DataTable columns={columns} rows={rows} rowKey={(c) => c.id} loading={q.isLoading} emptyTitle={t('campaigns.empty')} />
    </div>
  );
}
