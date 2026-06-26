import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Info } from 'lucide-react';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useBroadcastModeration } from '../hooks';
import type { BroadcastData, BroadcastModerationBody } from '@/types/broadcast.types';

type Mode = 'kick' | 'ban';
type TargetKind = 'user_id' | 'member';

interface Props {
  open: boolean;
  onClose: () => void;
  mode: Mode;
  broadcast: BroadcastData | null;
}

/**
 * نافذة إشراف لطرد/حظر مشاهد. لا سجلّ مشاهدين حيّ (لا نقطة لذلك) — المشغّل يُدخل الهدف:
 *   • user_id (رقم) لمستخدم مصادَق (إنفاذ قويّ)، أو
 *   • member (نصّ "u…/f…") لعضو حضور مُبهَم (أفضل-جهد).
 * الحظر يضيف مدّة (دقائق) وسبباً اختياريَّين.
 */
export function BroadcastModerationModal({ open, onClose, mode, broadcast }: Props) {
  const { t } = useTranslation('broadcast');
  const moderation = useBroadcastModeration();

  const [targetKind, setTargetKind] = useState<TargetKind>('user_id');
  const [userId, setUserId] = useState('');
  const [member, setMember] = useState('');
  const [durationMinutes, setDurationMinutes] = useState('');
  const [reason, setReason] = useState('');

  useEffect(() => {
    if (!open) return;
    setTargetKind('user_id');
    setUserId('');
    setMember('');
    setDurationMinutes('');
    setReason('');
  }, [open]);

  const targetValid = targetKind === 'user_id' ? userId.trim() !== '' && Number(userId) > 0 : member.trim() !== '';

  const submit = () => {
    if (!broadcast || !targetValid) return;
    const body: BroadcastModerationBody =
      targetKind === 'user_id' ? { user_id: Number(userId) } : { member: member.trim() };
    if (mode === 'ban') {
      if (durationMinutes.trim() !== '' && Number(durationMinutes) > 0) body.duration_minutes = Number(durationMinutes);
      if (reason.trim() !== '') body.reason = reason.trim();
    }
    moderation.mutate({ id: broadcast.id, action: mode, body }, { onSuccess: () => onClose() });
  };

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={mode === 'ban' ? t('moderation.banTitle') : t('moderation.kickTitle')}
      description={broadcast?.title}
      size="md"
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={moderation.isPending}>
            {t('common.cancel', { ns: 'common' })}
          </Button>
          <Button onClick={submit} disabled={!targetValid || moderation.isPending}>
            {mode === 'ban' ? t('moderation.banSubmit') : t('moderation.kickSubmit')}
          </Button>
        </>
      }
    >
      <div className="space-y-4">
        <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
          <Info className="mt-0.5 h-3.5 w-3.5 shrink-0" />
          {t('moderation.targetHint')}
        </p>

        <div className="flex gap-2">
          <button
            type="button"
            onClick={() => setTargetKind('user_id')}
            className={
              'flex-1 border px-3 py-2 text-sm font-medium ' +
              (targetKind === 'user_id' ? 'border-primary text-primary' : 'border-border text-muted-foreground')
            }
          >
            {t('moderation.byUserId')}
          </button>
          <button
            type="button"
            onClick={() => setTargetKind('member')}
            className={
              'flex-1 border px-3 py-2 text-sm font-medium ' +
              (targetKind === 'member' ? 'border-primary text-primary' : 'border-border text-muted-foreground')
            }
          >
            {t('moderation.byMember')}
          </button>
        </div>

        {targetKind === 'user_id' ? (
          <div>
            <Label htmlFor="mod-userid">{t('moderation.userIdLabel')}</Label>
            <Input
              id="mod-userid"
              type="number"
              min={1}
              value={userId}
              onChange={(e) => setUserId(e.target.value)}
              dir="ltr"
              placeholder={t('moderation.userIdPlaceholder')}
            />
          </div>
        ) : (
          <div>
            <Label htmlFor="mod-member">{t('moderation.memberLabel')}</Label>
            <Input
              id="mod-member"
              value={member}
              onChange={(e) => setMember(e.target.value)}
              dir="ltr"
              placeholder={t('moderation.memberPlaceholder')}
            />
            <p className="mt-1 text-xs text-muted-foreground">{t('moderation.memberHint')}</p>
          </div>
        )}

        {mode === 'ban' ? (
          <>
            <div>
              <Label htmlFor="mod-duration">{t('moderation.durationLabel')}</Label>
              <Input
                id="mod-duration"
                type="number"
                min={1}
                value={durationMinutes}
                onChange={(e) => setDurationMinutes(e.target.value)}
                dir="ltr"
                placeholder={t('moderation.durationPlaceholder')}
              />
              <p className="mt-1 text-xs text-muted-foreground">{t('moderation.durationHint')}</p>
            </div>
            <div>
              <Label htmlFor="mod-reason">{t('moderation.reasonLabel')}</Label>
              <Input
                id="mod-reason"
                value={reason}
                onChange={(e) => setReason(e.target.value)}
                placeholder={t('moderation.reasonPlaceholder')}
              />
            </div>
          </>
        ) : null}
      </div>
    </Modal>
  );
}
