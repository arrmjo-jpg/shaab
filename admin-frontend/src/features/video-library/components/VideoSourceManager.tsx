import { useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, Link2, Loader2, RefreshCw, UploadCloud, Video as VideoIcon } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { useToast } from '@/hooks/useToast';
import { mediaLibraryService } from '@/services/mediaLibrary.service';
import type { ExternalVideoResolved, MediaAssetData } from '@/types/content.types';
import type { VideoMediaRef, VideoSourceType } from '@/types/videoLibrary.types';

/** القيمة المُبلَّغة للنموذج: مصدر واحد فقط — مرفوع (asset id) أو خارجي (url). */
export interface SourceValue {
  mediaAssetId: number | null;
  sourceUrl: string | null;
}

interface Props {
  initialMedia?: VideoMediaRef | null;
  initialSourceType?: VideoSourceType | null;
  onChange: (v: SourceValue) => void;
  /** يبلّغ حالة المعالجة الحيّة (للنموذج كي يضبط بوّابة النشر). */
  onStatusChange?: (status: string | null) => void;
}

const PROCESSING = ['queued', 'processing'];

/**
 * مدير مصدر الفيديو — مصدر واحد من اثنين، مع أمان استبدال يحترم نموذج الملكية:
 *   • مرفوع: أصل مملوك يدخل خط HLS القياسي (بلا profile=reel — حدّ مدّة الفيديو الكامل).
 *   • خارجي: مرجع مكتبة مشترك (يوتيوب/فيميو/MP4) — يُحلّ خادمياً عند الحفظ.
 * لا «إزالة مصدر»: الواجهة الخلفية لا تفصل المصدر (الفيديو يملك مصدراً دائماً) —
 * العملية الوحيدة هي الاستبدال الصريح المؤكَّد، فلا UX يوحي بدلالات استبدال غير آمنة.
 */
export function VideoSourceManager({ initialMedia, initialSourceType, onChange, onStatusChange }: Props) {
  const { t } = useTranslation('videoLibrary');
  const { error, confirm } = useToast();
  const inputRef = useRef<HTMLInputElement>(null);
  const pollRef = useRef<ReturnType<typeof setInterval> | null>(null);

  const initialIsExternal =
    (initialMedia?.kind != null && initialMedia.kind === 'external') ||
    (initialSourceType != null && initialSourceType !== 'uploaded');

  const [open, setOpen] = useState(initialMedia == null);
  const [tab, setTab] = useState<'upload' | 'external'>(initialIsExternal ? 'external' : 'upload');

  // مصدر مرفوع مُلتقَط هذه الجلسة (أو المُحمَّل بالاستطلاع للمصدر الأولي).
  const [asset, setAsset] = useState<MediaAssetData | null>(null);
  const [uuid, setUuid] = useState<string | null>(initialMedia && !initialIsExternal ? initialMedia.uuid : null);
  const [uploading, setUploading] = useState(false);
  const [progress, setProgress] = useState(0);

  // مصدر خارجي مُلتقَط هذه الجلسة.
  const [url, setUrl] = useState('');
  const [resolved, setResolved] = useState<ExternalVideoResolved | null>(null);
  const [resolving, setResolving] = useState(false);

  const uploadedStatus = asset?.processing_status ?? (initialIsExternal ? null : initialMedia?.processing_status ?? null);
  const isProcessing = uploadedStatus !== null && PROCESSING.includes(uploadedStatus);
  const isFailed = uploadedStatus === 'failed';

  // أبلِغ النموذج بحالة المعالجة (الخارجي جاهز دائماً).
  useEffect(() => {
    if (resolved !== null || initialIsExternal) {
      onStatusChange?.('ready');
      return;
    }
    onStatusChange?.(uploadedStatus);
  }, [uploadedStatus, resolved, initialIsExternal, onStatusChange]);

  // استطلاع حالة معالجة الأصل المرفوع حتى ready/failed.
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
      // بلا profile=reel ⇒ خط HLS+poster القياسي بحدّ مدّة الفيديو الكامل.
      const a = await mediaLibraryService.upload(file, setProgress);
      setAsset(a);
      setUuid(a.uuid);
      setResolved(null);
      onChange({ mediaAssetId: a.id, sourceUrl: null });
      setOpen(false);
    } catch {
      error(t('form.source.uploadFailed'));
    } finally {
      setUploading(false);
    }
  };

  const onResolve = async () => {
    const trimmed = url.trim();
    if (trimmed === '') return;
    setResolving(true);
    try {
      const r = await mediaLibraryService.resolveExternal(trimmed);
      setResolved(r);
      setAsset(null);
      setUuid(null);
      onChange({ mediaAssetId: null, sourceUrl: trimmed });
      setOpen(false);
    } catch {
      error(t('form.source.resolveFailed'));
    } finally {
      setResolving(false);
    }
  };

  const requestReplace = async () => {
    if (
      await confirm({
        title: t('form.source.replaceTitle'),
        text: initialIsExternal || resolved !== null ? t('form.source.replaceTextExternal') : t('form.source.replaceTextUploaded'),
        confirmText: t('form.source.replaceConfirm'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    ) {
      setOpen(true);
    }
  };

  const reprocess = async () => {
    if (!uuid) return;
    setAsset(await mediaLibraryService.reprocess(uuid));
  };

  // ─── Current-source card (when picker closed) ─────────────────────────────
  const renderCurrent = () => {
    const isExternal = resolved !== null || (asset === null && initialIsExternal);
    const poster = resolved?.poster_url ?? asset?.poster ?? asset?.thumbnail?.jpg ?? initialMedia?.poster_url ?? null;
    const provider = resolved?.provider ?? initialMedia?.provider ?? null;

    return (
      <div className="flex items-center gap-3 border border-border bg-background p-3">
        <div className="flex h-20 w-14 shrink-0 items-center justify-center overflow-hidden border border-border bg-muted">
          {poster ? <img src={poster} alt="" className="h-full w-full object-cover" /> : <VideoIcon className="h-5 w-5 text-muted-foreground" />}
        </div>
        <div className="min-w-0 flex-1 space-y-1">
          {isExternal ? (
            <Badge variant="default" className="gap-1">
              <Link2 className="h-3 w-3" />
              {provider ? t(`source.${provider}`, { defaultValue: provider }) : t('form.source.external')}
            </Badge>
          ) : isProcessing ? (
            <Badge variant="muted" className="gap-1">
              <Loader2 className="h-3 w-3 animate-spin" />
              {t('processing.processing')}
            </Badge>
          ) : isFailed ? (
            <Badge variant="muted" className="text-destructive">{t('processing.failed')}</Badge>
          ) : (
            <Badge variant="success">{t('processing.ready')}</Badge>
          )}
          <div className="flex flex-wrap items-center gap-2">
            <Button type="button" variant="outline" size="sm" onClick={() => void requestReplace()}>
              <RefreshCw className="h-4 w-4" />
              {t('form.source.replace')}
            </Button>
            {isFailed ? (
              <Button type="button" variant="outline" size="sm" onClick={() => void reprocess()}>
                <RefreshCw className="h-4 w-4" />
                {t('form.source.retry')}
              </Button>
            ) : null}
          </div>
          {isFailed ? (
            <p className="flex items-center gap-1.5 text-xs text-destructive">
              <AlertTriangle className="h-3.5 w-3.5 shrink-0" />
              {t('form.source.failedHint')}
            </p>
          ) : isProcessing ? (
            <p className="text-xs text-muted-foreground">{t('form.source.processingHint')}</p>
          ) : null}
        </div>
      </div>
    );
  };

  return (
    <div className="space-y-3">
      <input
        ref={inputRef}
        type="file"
        accept="video/mp4,video/webm,video/quicktime"
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) void onPick(f);
          e.target.value = '';
        }}
      />

      {!open ? (
        renderCurrent()
      ) : (
        <div className="space-y-3 border border-border bg-background p-3">
          {/* Tabs */}
          <div className="flex gap-2">
            <button
              type="button"
              onClick={() => setTab('upload')}
              className={cn('flex-1 border px-3 py-2 text-sm font-medium', tab === 'upload' ? 'border-primary text-primary' : 'border-border text-muted-foreground')}
            >
              <UploadCloud className="me-1.5 inline h-4 w-4" />
              {t('form.source.tabUpload')}
            </button>
            <button
              type="button"
              onClick={() => setTab('external')}
              className={cn('flex-1 border px-3 py-2 text-sm font-medium', tab === 'external' ? 'border-primary text-primary' : 'border-border text-muted-foreground')}
            >
              <Link2 className="me-1.5 inline h-4 w-4" />
              {t('form.source.tabExternal')}
            </button>
          </div>

          {tab === 'upload' ? (
            <button
              type="button"
              onClick={() => inputRef.current?.click()}
              disabled={uploading}
              className="flex w-full flex-col items-center justify-center gap-2 border border-dashed border-border bg-background px-4 py-10 text-sm text-muted-foreground transition-colors hover:border-primary hover:text-primary disabled:opacity-50"
            >
              {uploading ? (
                <>
                  <Loader2 className="h-6 w-6 animate-spin" />
                  <span>{t('form.source.uploading', { percent: progress })}</span>
                </>
              ) : (
                <>
                  <UploadCloud className="h-6 w-6" />
                  <span>{t('form.source.uploadHint')}</span>
                </>
              )}
            </button>
          ) : (
            <div className="space-y-2">
              <div className="flex items-center gap-2">
                <Input
                  value={url}
                  onChange={(e) => setUrl(e.target.value)}
                  placeholder={t('form.source.urlPlaceholder')}
                  dir="ltr"
                />
                <Button type="button" variant="outline" onClick={() => void onResolve()} disabled={resolving || url.trim() === ''}>
                  {resolving ? <Loader2 className="h-4 w-4 animate-spin" /> : t('form.source.resolve')}
                </Button>
              </div>
              <p className="text-xs text-muted-foreground">{t('form.source.urlHint')}</p>
            </div>
          )}

          {initialMedia != null ? (
            <Button type="button" variant="ghost" size="sm" onClick={() => setOpen(false)}>
              {t('common.cancel', { ns: 'common' })}
            </Button>
          ) : null}
        </div>
      )}
    </div>
  );
}
