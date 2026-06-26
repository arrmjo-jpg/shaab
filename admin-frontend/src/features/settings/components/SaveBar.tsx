import { useTranslation } from 'react-i18next';
import { Save } from 'lucide-react';
import { Button } from '@/components/ui/button';

export function SaveBar({
  saving,
  disabled,
  note,
}: {
  saving: boolean;
  disabled?: boolean;
  note?: string;
}) {
  const { t } = useTranslation('settings');
  return (
    <div className="sticky bottom-4 z-10 flex items-center justify-between gap-3 rounded-2xl border border-border bg-background/90 px-4 py-3 shadow-soft backdrop-blur">
      <span className="text-xs text-muted-foreground">{note ?? ''}</span>
      <Button type="submit" disabled={saving || disabled}>
        <Save className="h-4 w-4" />
        {saving ? t('common.saving') : t('common.save')}
      </Button>
    </div>
  );
}
