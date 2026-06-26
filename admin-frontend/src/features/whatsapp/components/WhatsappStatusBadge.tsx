import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import type { WhatsappCampaignStatus } from '@/types/whatsapp.types';

const VARIANT: Record<WhatsappCampaignStatus, 'default' | 'success' | 'muted' | 'destructive'> = {
  draft: 'muted',
  scheduled: 'default',
  sending: 'default',
  completed: 'success',
  failed: 'destructive',
  cancelled: 'muted',
};

/** شارة حالة الحملة بلون دلاليّ موحّد. */
export function WhatsappStatusBadge({ status }: { status: WhatsappCampaignStatus }) {
  const { t } = useTranslation('whatsapp');
  return <Badge variant={VARIANT[status]}>{t(`campaigns.status.${status}`)}</Badge>;
}
