import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, Loader2, Play, RefreshCw, UploadCloud, Video, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { useToast } from '@/hooks/useToast';
import { mediaLibraryService } from '@/services/mediaLibrary.service';
import type { MediaAssetData, ReelMediaRef } from '@/types/content.types';
import { ReelProcessingChecklist } from './ReelProcessingChecklist';
import { ReelVideoPreviewModal } from './ReelVideoPreviewModal';

interface Props {
  initialMedia?: ReelMediaRef | null;
  onChange: (assetId: number | null) => void;
  /** Reports the live processing status so the form can gate publish. */
  onStatusChange?: (status: string | null) => void;
}

const PROCESSING = ['queued', 'processing'];

/**
 * رافع فيديو الريل — غلاف رفيع فوق تدفّق media_assets القائم مع profile=reel.
 * لا backend مخصّص ولا API منفصل: يستخدم mediaLibraryService.upload/get/reprocess.
 */
export function ReelVideoUploader({ initialMedia, onChange, onStatusChange }: Props) {
  const { t } = useTranslation('content');
  const { error, confirm } = useToast();
  const inputRef = useRef<HTMLInputElement>(null);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const [asset, setAsset] = useState<MediaAssetData | null>(null);
  const [uuid, setUuid] = useState<string | null>(initialMedia?.uuid ?? null);
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);
  const [playOpen, setPlayOpen] = useState(false);

  const status = asset?.processing_status ?? initialMedia?.processing_status ?? null;
  const isProcessing = status !== null && PROCESSING.includes(status);
  const isFailed = status === 'failed';

  // إبلاغ النموذج بحالة المعالجة الحيّة (لبوّابة النشر).
  useEffect(() => {
    onStatusChange?.(status);
  }, [status, onStatusChange]);

  // استطلاع حالة المعالجة عبر نقطة النهاية القائمة حتى ready/failed.
  useEffect(() => {
    const stop = () => {
      if (pollRef.current) {
        clearInterval(pollRef.current);
        pollRef.current = null;
      }
    };
    if (uuid === null || !isProcessing) {
      stop();
      return stop;
    }
    pollRef.current = setInterval(() => {
      void mediaLibraryService.get(uuid).then((a) => {
        setAsset(a);
        if (a.processing_status === null || !PROCESSING.includes(a.processing_status)) stop();
      });
    }, 4000);
    return stop;
  }, [uuid, isProcessing]);

  const onPick = async (file: File) => {
    setUploading(true);
    setProgress(0);
    try {
      const a = await mediaLibraryService.upload(file, setProgress, 'reel');
      setAsset(a);
      setUuid(a.uuid);
      onChange(a.id);
    } catch {
      error(t('reels.media.uploadFailed'));
    } finally {
      setUploading(false);
    }
  };

  const reprocess = async () => {
    if (!uuid) return;
    const a = await mediaLibraryService.reprocess(uuid);
    setAsset(a);
  };

  // الاستبدال يفصل الأصل الحالي — تأكيد صريح (قد يُنظَّف الملف القديم لاحقاً).
  const requestReplace = async () => {
    if (
      uuid !== null &&
      !(await confirm({
        title: t('reels.media.replaceTitle'),
        text: t('reels.media.replaceText'),
        confirmText: t('reels.media.replaceConfirm'),
        cancelText: t('common.cancel', { ns: 'common' }),
      }))
    ) {
      return;
    }
    inputRef.current?.click();
  };

  const requestRemove = async () => {
    if (
      await confirm({
        title: t('reels.media.removeTitle'),
        text: t('reels.media.removeText'),
        confirmText: t('reels.media.remove'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    ) {
      setAsset(null);
      setUuid(null);
      onChange(null);
    }
  };

  const thumb = asset?.thumbnail?.jpg ?? asset?.poster ?? null;
  const hasMedia = uuid !== null;
  const canPlay = status === 'ready' && uuid !== null;
  const transcode = asset?.processing ?? null;
  // اعرض القائمة الحبيبية أثناء المعالجة/الفشل، أو عند جاهزية جزئية (مشتقّات متخطّاة).
  const showChecklist =
    transcode !== null && (isProcessing || isFailed || transcode.completed < transcode.total);

  return (
    <div className="space-y-2">
      <input
        ref={inputRef}
        type="file"
        accept="video/mp4,video/webm"
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) void onPick(f);
          e.target.value = '';
        }}
      />

      {!hasMedia ? (
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          disabled={uploading}
          className="flex w-full flex-col items-center justify-center gap-2 border border-dashed border-border bg-background px-4 py-10 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-primary disabled:opacity-50"
        >
          {uploading ? (
            <>
              <Loader2 className="h-6 w-6 animate-spin" />
              <span>{t('reels.media.uploading', { percent: progress })}</span>
            </>
          ) : (
            <>
              <UploadCloud className="h-6 w-6" />
              <span>{t('reels.media.upload')}</span>
            </>
          )}
        </button>
      ) : (
        <div className="space-y-2 border border-border bg-background p-3">
          <div className="flex items-center gap-3">
            {canPlay ? (
              <button
                type="button"
                onClick={() => setPlayOpen(true)}
                aria-label={t('reels.action.preview')}
                className="group relative flex h-20 w-14 shrink-0 items-center justify-center overflow-hidden border border-border bg-muted"
              >
                {thumb ? (
                  <img src={thumb} alt="" className="h-full w-full object-cover" />
                ) : (
                  <Video className="h-5 w-5 text-muted-foreground" />
                )}
                <span className="absolute inset-0 flex items-center justify-center bg-foreground/30 opacity-0 transition-opacity group-hover:opacity-100">
                  <Play className="h-5 w-5 fill-white text-white" />
                </span>
              </button>
            ) : (
              <div className="flex h-20 w-14 shrink-0 items-center justify-center overflow-hidden border border-border bg-muted">
                {thumb ? (
                  <img src={thumb} alt="" className="h-full w-full object-cover" />
                ) : (
                  <Video className="h-5 w-5 text-muted-foreground" />
                )}
              </div>
            )}
            <div className="min-w-0 flex-1 space-y-1">
              <div className="flex items-center gap-2">
                {isProcessing ? (
                  <Badge variant="muted" className="gap-1">
                    <Loader2 className="h-3 w-3 animate-spin" />
                    {t('reels.media.processing')}
                  </Badge>
                ) : isFailed ? (
                  <Badge variant="muted" className="text-destructive">{t('reels.media.failed')}</Badge>
                ) : (
                  <Badge variant="success">{t('reels.media.ready')}</Badge>
                )}
                {asset?.duration ? (
                  <span className="text-xs text-muted-foreground">{asset.duration}s</span>
                ) : null}
              </div>
              <div className="flex flex-wrap items-center gap-2">
                {canPlay ? (
                  <Button variant="outline" size="sm" onClick={() => setPlayOpen(true)}>
                    <Play className="h-4 w-4" />
                    {t('reels.action.preview')}
                  </Button>
                ) : null}
                <Button variant="outline" size="sm" onClick={() => void requestReplace()} disabled={uploading}>
                  <UploadCloud className="h-4 w-4" />
                  {t('reels.media.replace')}
                </Button>
                {isFailed ? (
                  <Button variant="outline" size="sm" onClick={() => void reprocess()}>
                    <RefreshCw className="h-4 w-4" />
                    {t('reels.media.retry')}
                  </Button>
                ) : null}
                <Button variant="ghost" size="sm" className="text-destructive" onClick={() => void requestRemove()}>
                  <X className="h-4 w-4" />
                  {t('reels.media.remove')}
                </Button>
              </div>
            </div>
          </div>

          {isFailed ? (
            <p className="flex items-center gap-1.5 text-xs text-destructive">
              <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
              {t('reels.media.failedHint')}
            </p>
          ) : isProcessing ? (
            <p className="text-xs text-muted-foreground">{t('reels.media.processingHint')}</p>
          ) : null}

          {showChecklist && transcode ? <ReelProcessingChecklist progress={transcode} /> : null}
        </div>
      )}

      <ReelVideoPreviewModal
        uuid={playOpen ? uuid : null}
        title={t('reels.action.preview')}
        onClose={() => setPlayOpen(false)}
      />
    </div>
  );
}
