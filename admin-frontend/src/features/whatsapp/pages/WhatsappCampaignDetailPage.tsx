import { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useNavigate, useParams } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { ArrowRight, Ban, Eye, Send, TestTube } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { SelectField } from '@/components/form/SelectField';
import { DataTable, type Column } from '@/components/data/DataTable';
import { LoadingState, ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { paths } from '@/router/paths';
import { whatsappService } from '@/services/whatsapp.service';
import {
  useCancelWhatsappCampaign,
  useSendWhatsappCampaign,
  useWhatsappCampaign,
  useWhatsappCampaignMessages,
} from '../hooks';
import { WhatsappStatusBadge } from '../components/WhatsappStatusBadge';
import type { WhatsappCampaignMessageData } from '@/types/whatsapp.types';

/** تفاصيل الحملة — معاينة + اختبار لرقم + إرسال/إلغاء + سجلّ الرسائل بأسباب الفشل. */
export default function WhatsappCampaignDetailPage() {
  const { t } = useTranslation('whatsapp');
  const navigate = useNavigate();
  const { id: idParam } = useParams<{ id: string }>();
  const id = idParam ? Number(idParam) : null;

  const { hasPermission } = useAuth();
  const canSend = hasPermission('whatsapp.send');
  const { success, error: toastError, confirm } = useToast();

  const q = useWhatsappCampaign(id);
  const send = useSendWhatsappCampaign();
  const cancel = useCancelWhatsappCampaign();

  const [showPreview, setShowPreview] = useState(false);
  const [testPhone, setTestPhone] = useState('');
  const [testing, setTesting] = useState(false);
  const [msgStatus, setMsgStatus] = useState<'' | 'pending' | 'sent' | 'failed'>('');
  const [msgPage, setMsgPage] = useState(1);

  const previewQ = useQuery({
    queryKey: ['whatsapp', 'campaigns', 'preview', id],
    queryFn: () => whatsappService.previewCampaign(id as number),
    enabled: id !== null && showPreview,
  });

  const messagesQ = useWhatsappCampaignMessages(id, { page: msgPage, per_page: 20, status: msgStatus });

  if (q.isLoading) return <LoadingState />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const campaign = q.data;
  const canDispatch = campaign.status === 'draft' || campaign.status === 'scheduled';

  const onSend = async () => {
    if (
      await confirm({
        title: t('campaigns.confirm.sendTitle'),
        text: t('campaigns.confirm.sendText'),
        confirmText: t('campaigns.confirm.sendYes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      send.mutate(campaign.id, { onSuccess: () => success(t('campaigns.sentStarted')) });
  };

  const onTest = async () => {
    if (testPhone.trim() === '') {
      toastError(t('campaigns.form.phoneRequired'));
      return;
    }
    setTesting(true);
    try {
      const m = await whatsappService.testCampaign(campaign.id, testPhone.trim());
      success(m);
    } catch {
      toastError(t('campaigns.testFailed'));
    } finally {
      setTesting(false);
    }
  };

  const messages = messagesQ.data?.data ?? [];
  const msgPagination = messagesQ.data?.pagination ?? null;

  const columns: Column<WhatsappCampaignMessageData>[] = [
    { key: 'phone', header: t('campaigns.msg.phone'), render: (m) => <span dir="ltr" className="font-mono text-sm">{m.phone}</span> },
    {
      key: 'status',
      header: t('campaigns.msg.status'),
      render: (m) =>
        m.status === 'sent' ? (
          <Badge variant="success">{t('campaigns.msg.sent')}</Badge>
        ) : m.status === 'failed' ? (
          <Badge variant="destructive">{t('campaigns.msg.failed')}</Badge>
        ) : (
          <Badge variant="muted">{t('campaigns.msg.pending')}</Badge>
        ),
    },
    { key: 'error', header: t('campaigns.msg.reason'), render: (m) => <span className="text-xs text-muted-foreground">{m.error ?? ''}</span> },
  ];

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center gap-3">
        <Button variant="ghost" size="icon" aria-label={t('common.back', { ns: 'common' })} onClick={() => navigate(paths.whatsappCampaigns)}>
          <ArrowRight className="h-5 w-5" />
        </Button>
        <div className="flex-1">
          <h1 className="text-2xl font-bold">{campaign.name}</h1>
          <div className="mt-1 flex items-center gap-2 text-sm text-muted-foreground">
            <Badge variant="muted">{t(`campaigns.type.${campaign.type}`)}</Badge>
            <WhatsappStatusBadge status={campaign.status} />
            {campaign.scheduled_at ? <span>{t('campaigns.scheduledFor')}: {new Date(campaign.scheduled_at).toLocaleString()}</span> : null}
          </div>
        </div>
      </header>

      {/* عدّادات السجلّ */}
      <div className="grid grid-cols-3 gap-3 sm:grid-cols-3">
        <Stat label={t('campaigns.col.recipients')} value={campaign.recipients_total} />
        <Stat label={t('campaigns.col.sent')} value={campaign.sent_count} tone="text-emerald-600" />
        <Stat label={t('campaigns.col.failed')} value={campaign.failed_count} tone="text-destructive" />
      </div>

      {/* الإجراءات */}
      {canSend ? (
        <div className="flex flex-wrap items-center gap-2 border border-border p-4">
          <Button variant="outline" onClick={() => setShowPreview((s) => !s)}>
            <Eye className="h-4 w-4" />
            {t('campaigns.preview')}
          </Button>
          {canDispatch ? (
            <>
              <Button onClick={() => void onSend()} disabled={send.isPending}>
                <Send className="h-4 w-4" />
                {t('campaigns.sendNow')}
              </Button>
              <Button variant="outline" onClick={() => cancel.mutate(campaign.id)} disabled={cancel.isPending}>
                <Ban className="h-4 w-4" />
                {t('campaigns.cancel')}
              </Button>
            </>
          ) : null}
          <div className="flex items-center gap-2 ms-auto">
            <Input value={testPhone} onChange={(e) => setTestPhone(e.target.value)} dir="ltr" placeholder="+9627XXXXXXXX" className="w-44" />
            <Button variant="outline" onClick={() => void onTest()} disabled={testing}>
              <TestTube className="h-4 w-4" />
              {t('campaigns.test')}
            </Button>
          </div>
        </div>
      ) : null}

      {/* المعاينة */}
      {showPreview ? (
        <div className="border border-border p-4">
          <h2 className="mb-2 text-sm font-semibold text-muted-foreground">{t('campaigns.previewTitle')}</h2>
          {previewQ.isLoading ? (
            <LoadingState />
          ) : previewQ.data ? (
            <div className="space-y-2">
              {previewQ.data.media_url ? (
                <img src={previewQ.data.media_url} alt="" className="max-h-48 border border-border object-contain" />
              ) : null}
              <pre className="whitespace-pre-wrap break-words bg-muted/40 p-3 text-sm">{previewQ.data.text}</pre>
              <p className="text-xs text-muted-foreground">{t('campaigns.form.recipients')}: {previewQ.data.recipients}</p>
            </div>
          ) : null}
        </div>
      ) : null}

      {/* سجلّ الرسائل */}
      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">{t('campaigns.messagesTitle')}</h2>
          <SelectField
            label=""
            aria-label={t('campaigns.filterStatus')}
            value={msgStatus}
            onChange={(e) => {
              setMsgStatus(e.target.value as typeof msgStatus);
              setMsgPage(1);
            }}
            options={[
              { value: '', label: t('campaigns.allStatuses') },
              { value: 'sent', label: t('campaigns.msg.sent') },
              { value: 'failed', label: t('campaigns.msg.failed') },
              { value: 'pending', label: t('campaigns.msg.pending') },
            ]}
          />
        </div>
        <DataTable columns={columns} rows={messages} rowKey={(m) => m.id} loading={messagesQ.isLoading} emptyTitle={t('campaigns.noMessages')} />
        {msgPagination && msgPagination.total_pages > 1 ? (
          <div className="flex items-center justify-between text-sm text-muted-foreground">
            <span>{t('contacts.pageInfo', { page: msgPagination.current_page, pages: msgPagination.total_pages, total: msgPagination.total })}</span>
            <div className="flex gap-2">
              <Button variant="outline" size="sm" disabled={msgPagination.current_page <= 1} onClick={() => setMsgPage((p) => p - 1)}>
                {t('contacts.prev')}
              </Button>
              <Button variant="outline" size="sm" disabled={msgPagination.current_page >= msgPagination.total_pages} onClick={() => setMsgPage((p) => p + 1)}>
                {t('contacts.next')}
              </Button>
            </div>
          </div>
        ) : null}
      </div>
    </div>
  );
}

function Stat({ label, value, tone }: { label: string; value: number; tone?: string }) {
  return (
    <div className="border border-border p-3 text-center">
      <p className={`text-2xl font-bold ${tone ?? ''}`}>{value}</p>
      <p className="text-xs text-muted-foreground">{label}</p>
    </div>
  );
}
