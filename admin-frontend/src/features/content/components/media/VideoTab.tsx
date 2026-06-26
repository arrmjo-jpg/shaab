import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertCircle, Check, Loader2, RotateCw, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/useToast';
import { mediaLibraryService } from '@/services/mediaLibrary.service';
import { UploadProgress } from './UploadButton';
import { Dropzone } from './Dropzone';
import { HoverVideoPreview } from './HoverVideoPreview';
import type { MediaStaging } from '../../lib/useMediaStaging';
import type { NormalizedError } from '@/types/api';
import type {
  ExternalVideoResolved,
  StagedMediaItem,
  VideoProcessingStatus,
} from '@/types/content.types';

const ACCEPT = 'video/mp4,video/webm';
const POLL_MS = 4000;
const PENDING: VideoProcessingStatus[] = ['queued', 'processing'];

interface Props {
  staging: MediaStaging;
}

function formatDuration(seconds?: number | null): string | null {
  if (!seconds || seconds <= 0) return null;
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  return `${m}:${String(s).padStart(2, '0')}`;
}

/** Video workspace — uploaded video (HLS pipeline) + external provider video. */
export function VideoTab({ staging }: Props) {
  const { t } = useTranslation('content');
  const { error } = useToast();
  const videoUploads = staging.uploading.filter((u) => u.target === 'video');

  const retry = (uuid?: string | null) => {
    if (!uuid) return;
    mediaLibraryService
      .reprocess(uuid)
      .then((asset) => staging.updateItem(asset))
      .catch((e: NormalizedError) => error(e?.message ?? ''));
  };

  // Poll uploaded videos that are still queued/processing until terminal.
  const pending = staging.videos.filter(
    (v) => v.uuid && v.processingStatus && PENDING.includes(v.processingStatus),
  );
  const pendingKey = pending.map((v) => v.uuid).join(',');

  useEffect(() => {
    if (pendingKey === '') return;
    const tick = () => {
      pendingKey.split(',').forEach((uuid) => {
        if (!uuid) return;
        mediaLibraryService
          .get(uuid)
          .then((asset) => staging.updateItem(asset))
          .catch(() => undefined);
      });
    };
    const handle = window.setInterval(tick, POLL_MS);
    return () => window.clearInterval(handle);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [pendingKey]);

  return (
    <div className="space-y-5">
      <Dropzone
        accept={ACCEPT}
        label={t('mediaStudio.video.drop')}
        hint={t('mediaStudio.video.dropHint')}
        onFiles={staging.uploadVideos}
      />

      <ExternalVideoInput staging={staging} />

      {staging.videos.length === 0 && videoUploads.length === 0 ? (
        <p className="text-xs text-muted-foreground">{t('mediaStudio.video.empty')}</p>
      ) : (
        <ul className="space-y-2">
          {staging.videos.map((v) => (
            <VideoRow
              key={v.assetId}
              item={v}
              onRemove={() => staging.remove(v.assetId)}
              onRetry={() => retry(v.uuid)}
            />
          ))}

          {videoUploads.map((u) => (
            <li key={u.tempId} className="border border-border bg-muted/30 p-2">
              <div className="mb-1 flex items-center justify-between text-xs text-muted-foreground">
                <span>{u.name}</span>
                <span>{u.error ? '—' : `${u.progress}%`}</span>
              </div>
              {!u.error ? <UploadProgress percent={u.progress} /> : null}
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}

const STATUS_VARIANT: Record<VideoProcessingStatus, 'default' | 'success' | 'muted' | 'destructive'> = {
  queued: 'muted',
  processing: 'default',
  ready: 'success',
  failed: 'destructive',
};

function VideoRow({
  item,
  onRemove,
  onRetry,
}: {
  item: StagedMediaItem;
  onRemove: () => void;
  onRetry: () => void;
}) {
  const { t } = useTranslation('content');
  const duration = formatDuration(item.duration);
  const status = item.processingStatus;
  const pending = status === 'queued' || status === 'processing';

  return (
    <li className="flex items-center gap-3 border border-border bg-background p-2">
      {/* Poster / hover preview */}
      <HoverVideoPreview
        className="h-12 w-20 shrink-0"
        poster={item.poster ?? null}
        videoSrc={item.url}
        enabled={!item.external}
        showPlayIcon={!pending}
        playIconSize="sm"
      >
        {pending ? (
          <span className="absolute inset-0 flex items-center justify-center bg-background/60">
            <Loader2 className="h-4 w-4 animate-spin text-primary" />
          </span>
        ) : null}
      </HoverVideoPreview>

      <div className="flex min-w-0 flex-1 flex-col gap-1">
        <span className="flex items-center gap-2">
          {item.external && item.provider ? (
            <Badge variant="default" className="uppercase">
              {item.provider}
            </Badge>
          ) : null}
          {status ? (
            <Badge variant={STATUS_VARIANT[status]}>{t(`mediaStudio.video.status.${status}`)}</Badge>
          ) : null}
          {duration ? <span className="text-xs text-muted-foreground">{duration}</span> : null}
        </span>
        <span className="truncate text-sm">{item.name ?? item.url ?? `#${item.assetId}`}</span>
        {pending ? (
          <span className="text-[11px] text-muted-foreground">
            {t('mediaStudio.video.processingHint')}
          </span>
        ) : null}
      </div>

      {status === 'failed' ? (
        <button
          type="button"
          title={t('mediaStudio.video.retry')}
          onClick={onRetry}
          className="shrink-0 text-muted-foreground hover:text-primary"
        >
          <RotateCw className="h-4 w-4" />
        </button>
      ) : null}

      <button
        type="button"
        title={t('mediaStudio.common.remove')}
        onClick={onRemove}
        className="shrink-0 text-muted-foreground hover:text-destructive"
      >
        <Trash2 className="h-4 w-4" />
      </button>
    </li>
  );
}

type Status = 'idle' | 'detecting' | 'valid' | 'invalid';

function ExternalVideoInput({ staging }: { staging: MediaStaging }) {
  const { t } = useTranslation('content');
  const { success, error } = useToast();
  const [url, setUrl] = useState('');
  const [status, setStatus] = useState<Status>('idle');
  const [resolved, setResolved] = useState<ExternalVideoResolved | null>(null);
  const [adding, setAdding] = useState(false);

  // Debounced provider detection (covers paste + typing).
  useEffect(() => {
    const value = url.trim();
    if (value === '') {
      setStatus('idle');
      setResolved(null);
      return;
    }
    setStatus('detecting');
    const handle = window.setTimeout(() => {
      mediaLibraryService
        .resolveExternal(value)
        .then((r) => {
          setResolved(r);
          setStatus('valid');
        })
        .catch(() => {
          setResolved(null);
          setStatus('invalid');
        });
    }, 400);
    return () => window.clearTimeout(handle);
  }, [url]);

  const add = () => {
    if (status !== 'valid') return;
    setAdding(true);
    mediaLibraryService
      .storeExternal(url.trim())
      .then((asset) => {
        staging.addExternalVideo(asset);
        success(t('mediaStudio.video.added'));
        setUrl('');
        setResolved(null);
        setStatus('idle');
      })
      .catch((e: NormalizedError) => error(e?.message ?? t('mediaStudio.video.invalid')))
      .finally(() => setAdding(false));
  };

  return (
    <div className="space-y-3 border border-border bg-background p-3">
      <p className="text-xs font-bold text-muted-foreground">{t('mediaStudio.video.external')}</p>

      <div className="flex flex-wrap items-center gap-2">
        <Input
          value={url}
          onChange={(e) => setUrl(e.target.value)}
          placeholder={t('mediaStudio.video.externalPlaceholder')}
          className="min-w-0 flex-1"
        />
        <Button type="button" onClick={add} disabled={status !== 'valid' || adding}>
          {adding ? <Loader2 className="h-4 w-4 animate-spin" /> : null}
          {t('mediaStudio.video.add')}
        </Button>
      </div>

      {status === 'detecting' ? (
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Loader2 className="h-3.5 w-3.5 animate-spin" />
          {t('mediaStudio.video.detecting')}
        </p>
      ) : status === 'invalid' ? (
        <p className="flex items-center gap-1.5 text-xs text-destructive">
          <AlertCircle className="h-3.5 w-3.5" />
          {t('mediaStudio.video.invalid')}
        </p>
      ) : status === 'valid' && resolved ? (
        <div className="space-y-2">
          <p className="flex items-center gap-1.5 text-xs text-primary">
            <Check className="h-3.5 w-3.5" />
            <Badge variant="default" className="uppercase">
              {resolved.provider}
            </Badge>
          </p>
          <VideoPreview provider={resolved.provider} embedUrl={resolved.embed_url} />
        </div>
      ) : (
        <p className="text-xs text-muted-foreground">{t('mediaStudio.video.externalHint')}</p>
      )}
    </div>
  );
}

function VideoPreview({ provider, embedUrl }: { provider: string; embedUrl: string }) {
  if (provider === 'mp4') {
    return (
      // eslint-disable-next-line jsx-a11y/media-has-caption
      <video src={embedUrl} controls className="aspect-video w-full bg-black" />
    );
  }
  return (
    <iframe
      src={embedUrl}
      title="video-preview"
      className="aspect-video w-full border border-border"
      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
      allowFullScreen
    />
  );
}
