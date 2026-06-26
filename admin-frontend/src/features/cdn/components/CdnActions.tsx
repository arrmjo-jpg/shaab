import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { PlugZap, Trash2, Eraser } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useToast } from '@/hooks/useToast';
import { SettingsSection } from '@/features/settings/components/SettingsSection';
import { useCdnTest, usePurge, usePurgeAll } from '../hooks';

export function CdnActions({ canPurge }: { canPurge: boolean }) {
  const { t } = useTranslation('cdn');
  const { error, confirm } = useToast();
  const test = useCdnTest();
  const purge = usePurge();
  const purgeAll = usePurgeAll();
  const [urlsText, setUrlsText] = useState('');

  const runPurge = () => {
    const urls = urlsText
      .split('\n')
      .map((u) => u.trim())
      .filter(Boolean);
    if (urls.length === 0) {
      error(t('actions.urlsRequired'));
      return;
    }
    purge.mutate(urls, { onSuccess: () => setUrlsText('') });
  };

  const runPurgeAll = async () => {
    const ok = await confirm({
      title: t('actions.purgeAllConfirmTitle'),
      text: t('actions.purgeAllConfirmText'),
      confirmText: t('actions.confirm'),
      cancelText: t('actions.cancel'),
    });
    if (ok) purgeAll.mutate();
  };

  return (
    <SettingsSection title={t('actions.title')}>
      <Button
        type="button"
        variant="outline"
        onClick={() => test.mutate()}
        disabled={test.isPending}
      >
        <PlugZap className="h-4 w-4" />
        {test.isPending ? t('actions.testing') : t('actions.test')}
      </Button>

      <div className="space-y-1.5">
        <label className="text-sm font-medium">{t('actions.purgeUrls')}</label>
        <textarea
          rows={4}
          value={urlsText}
          onChange={(e) => setUrlsText(e.target.value)}
          placeholder={t('actions.purgeUrlsHint')}
          disabled={!canPurge}
          className="flex w-full border border-input bg-background px-3.5 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:opacity-50"
        />
        <Button
          type="button"
          onClick={runPurge}
          disabled={!canPurge || purge.isPending}
        >
          <Eraser className="h-4 w-4" />
          {purge.isPending ? t('actions.purging') : t('actions.purgeUrls')}
        </Button>
      </div>

      <div className="border-t border-border pt-4">
        <Button
          type="button"
          variant="destructive"
          onClick={runPurgeAll}
          disabled={!canPurge || purgeAll.isPending}
        >
          <Trash2 className="h-4 w-4" />
          {purgeAll.isPending ? t('actions.purging') : t('actions.purgeAll')}
        </Button>
        {!canPurge ? (
          <p className="mt-2 text-xs text-muted-foreground">{t('actions.noPurgePermission')}</p>
        ) : null}
      </div>
    </SettingsSection>
  );
}
