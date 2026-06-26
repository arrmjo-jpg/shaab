import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Trash2, ExternalLink } from 'lucide-react';
import { HoverVideoPreview } from './HoverVideoPreview';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { LoadingState, ErrorState } from '@/components/feedback';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useMediaAsset, useUpdateMediaAsset, useDeleteMediaAsset } from '../../hooks';
import type { MediaAssetData, MediaAssetUsage } from '@/types/content.types';
import type { NormalizedError } from '@/types/api';

interface Props {
  uuid: string | null;
  open: boolean;
  onClose: () => void;
}

function formatBytes(bytes: number): string {
  if (!bytes) return '—';
  const units = ['B', 'KB', 'MB', 'GB'];
  let val = bytes;
  let i = 0;
  while (val >= 1024 && i < units.length - 1) {
    val /= 1024;
    i += 1;
  }
  return `${val.toFixed(val < 10 && i > 0 ? 1 : 0)} ${units[i]}`;
}

function formatDuration(seconds: number | null): string | null {
  if (!seconds) return null;
  const m = Math.floor(seconds / 60);
  const s = Math.floor(seconds % 60);
  return `${m}:${s.toString().padStart(2, '0')}`;
}

/** Detail + governance panel: preview, metadata editing, file info, usage, delete. */
export function MediaAssetDetailPanel({ uuid, open, onClose }: Props) {
  const { t, i18n } = useTranslation('content');
  const { hasPermission } = useAuth();
  const { success, error, confirm } = useToast();

  const canEdit = hasPermission('media.upload');
  const canDelete = hasPermission('media.delete');

  const q = useMediaAsset(open ? uuid : null);
  const update = useUpdateMediaAsset();
  const del = useDeleteMediaAsset();

  const asset = q.data;

  const [alt, setAlt] = useState('');
  const [caption, setCaption] = useState('');
  const [credit, setCredit] = useState('');
  const [source, setSource] = useState('');

  // Seed the metadata form whenever a new asset loads.
  useEffect(() => {
    if (!asset) return;
    setAlt(asset.alt ?? '');
    setCaption(asset.caption ?? '');
    setCredit(asset.credit ?? '');
    setSource(asset.source ?? '');
  }, [asset]);

  const onSave = () => {
    if (!asset) return;
    update.mutate(
      {
        uuid: asset.uuid,
        payload: {
          alt: alt.trim() || null,
          caption: caption.trim() || null,
          credit: credit.trim() || null,
          source: source.trim() || null,
        },
      },
      { onSuccess: () => success(t('mediaLibrary.detail.saved')) },
    );
  };

  const onDelete = async () => {
    if (!asset) return;
    const ok = await confirm({
      title: t('mediaLibrary.delete.title'),
      text: t('mediaLibrary.delete.text'),
      confirmText: t('mediaLibrary.delete.yes'),
      cancelText: t('common.cancel', { ns: 'common' }),
    });
    if (!ok) return;
    try {
      await del.mutateAsync({ uuid: asset.uuid });
      onClose();
    } catch (e) {
      const err = e as NormalizedError;
      if (err.status === 409) {
        const count = Number((err.errors as Record<string, unknown>)?.usage_count ?? asset.usage_count ?? 0);
        const force = await confirm({
          title: t('mediaLibrary.delete.inUseTitle'),
          text: t('mediaLibrary.delete.inUseText', { count }),
          confirmText: t('mediaLibrary.delete.forceYes'),
          cancelText: t('common.cancel', { ns: 'common' }),
        });
        if (!force) return;
        try {
          await del.mutateAsync({ uuid: asset.uuid, force: true });
          onClose();
        } catch (e2) {
          error((e2 as NormalizedError).message);
        }
      } else {
        error(err.message);
      }
    }
  };

  return (
    <Modal open={open} onClose={onClose} title={t('mediaLibrary.detail.title')} size="xl">
      {q.isLoading || !asset ? (
        q.isError ? (
          <ErrorState onRetry={() => void q.refetch()} />
        ) : (
          <LoadingState />
        )
      ) : (
        <div className="grid gap-6 md:grid-cols-2">
          {/* Preview + file info */}
          <div className="space-y-4">
            <Preview asset={asset} />
            <FileInfo asset={asset} locale={i18n.language} />
            <Usage usages={asset.usages ?? []} t={t} />
          </div>

          {/* Metadata editing */}
          <div className="space-y-4">
            <h3 className="text-sm font-semibold text-foreground">
              {t('mediaLibrary.detail.metadata')}
            </h3>
            <div className="space-y-1.5">
              <Label htmlFor="m-alt">{t('mediaLibrary.detail.alt')}</Label>
              <Input
                id="m-alt"
                value={alt}
                onChange={(e) => setAlt(e.target.value)}
                disabled={!canEdit}
              />
              <p className="text-xs text-muted-foreground">{t('mediaLibrary.detail.altHint')}</p>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="m-caption">{t('mediaLibrary.detail.caption')}</Label>
              <textarea
                id="m-caption"
                value={caption}
                onChange={(e) => setCaption(e.target.value)}
                disabled={!canEdit}
                rows={3}
                className="flex w-full rounded-xl border border-input bg-background px-3.5 py-2 text-sm transition-colors placeholder:text-muted-foreground/70 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="m-credit">{t('mediaLibrary.detail.credit')}</Label>
              <Input
                id="m-credit"
                value={credit}
                onChange={(e) => setCredit(e.target.value)}
                disabled={!canEdit}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="m-source">{t('mediaLibrary.detail.source')}</Label>
              <Input
                id="m-source"
                value={source}
                onChange={(e) => setSource(e.target.value)}
                disabled={!canEdit}
              />
            </div>

            <div className="flex items-center justify-between gap-3 pt-2">
              {canDelete ? (
                <Button variant="destructive" onClick={() => void onDelete()} disabled={del.isPending}>
                  <Trash2 className="h-4 w-4" />
                  {t('mediaLibrary.delete.action')}
                </Button>
              ) : (
                <span />
              )}
              {canEdit ? (
                <Button onClick={onSave} disabled={update.isPending}>
                  {update.isPending
                    ? t('mediaLibrary.detail.saving')
                    : t('mediaLibrary.detail.save')}
                </Button>
              ) : null}
            </div>
          </div>
        </div>
      )}
    </Modal>
  );
}

function Preview({ asset }: { asset: MediaAssetData }) {
  const isUploadedVideo = asset.is_video && !asset.is_external;
  const isVideo = asset.is_video || asset.is_external;
  const poster =
    asset.medium ?? asset.thumb ?? asset.poster ?? (asset.is_image ? asset.url : null);

  return (
    <HoverVideoPreview
      className="aspect-video border border-border"
      fit="contain"
      poster={poster}
      videoSrc={asset.url}
      enabled={isUploadedVideo}
      alt={asset.alt ?? asset.original_name}
      showPlayIcon={isVideo}
    />
  );
}

function FileInfo({ asset, locale }: { asset: MediaAssetData; locale: string }) {
  const { t } = useTranslation('content');
  const duration = formatDuration(asset.duration);
  const created = asset.created_at
    ? new Date(asset.created_at).toLocaleString(locale === 'ar' ? 'ar' : 'en')
    : '—';

  const rows: Array<[string, string | null]> = [
    [t('mediaLibrary.detail.dimensions'), asset.width && asset.height ? `${asset.width}×${asset.height}` : null],
    [t('mediaLibrary.detail.mime'), asset.mime_type],
    [t('mediaLibrary.detail.size'), asset.size ? formatBytes(asset.size) : null],
    [t('mediaLibrary.detail.duration'), duration],
    [t('mediaLibrary.detail.provider'), asset.provider],
    [t('mediaLibrary.detail.processing'), asset.processing_status],
    [t('mediaLibrary.detail.created'), created],
    [t('mediaLibrary.detail.checksum'), asset.checksum ? asset.checksum.slice(0, 16) + '…' : null],
  ];

  return (
    <div className="space-y-2">
      <h3 className="text-sm font-semibold text-foreground">{t('mediaLibrary.detail.fileInfo')}</h3>
      <dl className="divide-y divide-border border border-border">
        <div className="flex items-center justify-between gap-3 px-3 py-1.5 text-xs">
          <dt className="text-muted-foreground">{asset.original_name}</dt>
        </div>
        {rows
          .filter(([, v]) => v)
          .map(([label, value]) => (
            <div key={label} className="flex items-center justify-between gap-3 px-3 py-1.5 text-xs">
              <dt className="text-muted-foreground">{label}</dt>
              <dd className="truncate font-medium" dir="ltr">
                {value}
              </dd>
            </div>
          ))}
      </dl>
    </div>
  );
}

function Usage({
  usages,
  t,
}: {
  usages: MediaAssetUsage[];
  t: ReturnType<typeof useTranslation>['t'];
}) {
  return (
    <div className="space-y-2">
      <h3 className="text-sm font-semibold text-foreground">
        {t('mediaLibrary.detail.usage')}{' '}
        <span className="text-muted-foreground">({usages.length})</span>
      </h3>
      {usages.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('mediaLibrary.detail.noUsage')}</p>
      ) : (
        <ul className="space-y-1.5">
          {usages.map((u, idx) => (
            <li
              key={`${u.context}-${u.id}-${idx}`}
              className="flex items-center justify-between gap-2 border border-border px-3 py-1.5 text-xs"
            >
              <span className="flex items-center gap-2">
                <ExternalLink className="h-3.5 w-3.5 text-muted-foreground" />
                <span className="truncate">{u.title ?? t('mediaLibrary.detail.untitled')}</span>
              </span>
              <Badge variant="muted">
                {u.context === 'article'
                  ? t('mediaLibrary.detail.ctxArticle')
                  : t('mediaLibrary.detail.ctxLiveUpdate')}
              </Badge>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
