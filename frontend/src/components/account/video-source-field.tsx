'use client';

import { useEffect, useRef, useState } from 'react';

import { CloseIcon, VideoIcon } from '@/components/icons';

export interface VideoSource {
  mediaAssetId: number | null;
  sourceUrl: string | null;
}

const VIDEO_ACCEPT = ['video/mp4', 'video/webm'];
const DEFAULT_MAX = 256 * 1024 * 1024; // 256MB (الفيديو العام)

const STATUS_LABEL: Record<string, string> = {
  queued: 'في قائمة الانتظار',
  processing: 'جارٍ المعالجة…',
  ready: 'جاهز للعرض',
  failed: 'فشلت المعالجة',
};

function formatSize(bytes?: number): string {
  if (!bytes) return '';
  const mb = bytes / (1024 * 1024);
  return mb >= 1 ? `${mb.toFixed(1)} م.ب` : `${Math.max(1, Math.round(bytes / 1024))} ك.ب`;
}

function formatDuration(sec?: number | null): string {
  if (sec === null || sec === undefined) return '';
  const m = Math.floor(sec / 60);
  const s = Math.floor(sec % 60);
  return `${m}:${String(s).padStart(2, '0')}`;
}

/**
 * مصدر فيديو/ريل — مثل الإدارة: **رفع ملفّ** (عبر طبقة ملكيّة الكاتب، مع حالة معالجة
 * حقيقيّة) أو **رابط خارجيّ** (يوتيوب/فيميو/MP4) عند السماح. للريل: رفع فقط + ملف معالجة
 * reel. الرفع يمرّ حصراً عبر /api/media (نفس TranscodeVideoAssetJob) — لا منطق رفع جديد.
 */
export function VideoSourceField({
  onChange,
  profile,
  allowLink = true,
  maxBytes = DEFAULT_MAX,
  uploadLabel,
}: {
  onChange: (s: VideoSource) => void;
  profile?: string; // 'reel' → ملف معالجة الريل
  allowLink?: boolean;
  maxBytes?: number;
  uploadLabel?: string;
}) {
  const [mode, setMode] = useState<'upload' | 'link'>('upload');
  const fileRef = useRef<HTMLInputElement>(null);
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [asset, setAsset] = useState<{ id: number; uuid: string; name: string; size?: number } | null>(null);
  const [status, setStatus] = useState<string | null>(null);
  const [duration, setDuration] = useState<number | null>(null);
  const [url, setUrl] = useState('');

  const maxMb = Math.round(maxBytes / (1024 * 1024));
  const defaultLabel = `اختر ملفّ فيديو (MP4 / WebM، حتى ${maxMb} م.ب)`;

  // استطلاع حالة المعالجة بعد الرفع (حتى ready/failed).
  useEffect(() => {
    if (!asset?.uuid || status === 'ready' || status === 'failed') return;
    let active = true;
    const tick = async () => {
      try {
        const res = await fetch(`/api/media/${asset.uuid}`);
        const data: { asset?: { processing_status?: string; duration?: number | null } } = await res
          .json()
          .catch(() => ({}));
        if (!active || !data.asset) return;
        if (data.asset.processing_status) setStatus(data.asset.processing_status);
        if (typeof data.asset.duration === 'number') setDuration(data.asset.duration);
      } catch {
        /* الشبكة — نعيد المحاولة لاحقاً */
      }
    };
    void tick();
    const interval = setInterval(tick, 3000);
    return () => {
      active = false;
      clearInterval(interval);
    };
  }, [asset?.uuid, status]);

  async function uploadFile(file: File) {
    setError(null);
    if (!VIDEO_ACCEPT.includes(file.type)) {
      setError('صيغة فيديو غير مدعومة (MP4 أو WebM).');
      return;
    }
    if (file.size > maxBytes) {
      setError(`حجم الفيديو يتجاوز ${maxMb} ميغابايت.`);
      return;
    }
    setUploading(true);
    try {
      const fd = new FormData();
      fd.append('file', file);
      if (profile) fd.append('profile', profile);
      const res = await fetch('/api/media', { method: 'POST', body: fd });
      const data: {
        success?: boolean;
        message?: string;
        asset?: { id?: number; uuid?: string; processing_status?: string };
      } = await res.json().catch(() => ({}));
      if (!res.ok || !data.success || !data.asset?.id || !data.asset.uuid) {
        setError(data.message || 'تعذّر رفع الفيديو.');
        return;
      }
      setAsset({ id: data.asset.id, uuid: data.asset.uuid, name: file.name, size: file.size });
      setStatus(data.asset.processing_status ?? 'queued');
      onChange({ mediaAssetId: data.asset.id, sourceUrl: null });
    } catch {
      setError('تعذّر الاتصال بالخادم.');
    } finally {
      setUploading(false);
    }
  }

  function clearUpload() {
    setAsset(null);
    setStatus(null);
    setDuration(null);
    if (fileRef.current) fileRef.current.value = '';
    onChange({ mediaAssetId: null, sourceUrl: null });
  }

  function switchMode(m: 'upload' | 'link') {
    if (m === mode) return;
    setMode(m);
    setError(null);
    setAsset(null);
    setStatus(null);
    setDuration(null);
    setUrl('');
    if (fileRef.current) fileRef.current.value = '';
    onChange({ mediaAssetId: null, sourceUrl: null });
  }

  const TAB = 'flex-1 border px-3 py-2 text-sm font-medium transition-colors';

  return (
    <div className="flex flex-col gap-2">
      <span className="text-sm font-medium text-fg">
        {allowLink ? 'مصدر الفيديو' : 'فيديو الريل'} <span className="text-danger">*</span>
      </span>

      {allowLink && (
        <div className="flex gap-1">
          <button
            type="button"
            onClick={() => switchMode('upload')}
            className={`${TAB} ${mode === 'upload' ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-surface text-muted'}`}
          >
            رفع فيديو
          </button>
          <button
            type="button"
            onClick={() => switchMode('link')}
            className={`${TAB} ${mode === 'link' ? 'border-primary bg-primary/10 text-primary' : 'border-border bg-surface text-muted'}`}
          >
            رابط خارجيّ
          </button>
        </div>
      )}

      {mode === 'upload' || !allowLink ? (
        asset ? (
          <div className="flex flex-col gap-2 border border-border bg-surface p-3">
            <div className="flex items-center justify-between gap-3">
              <div className="flex min-w-0 items-center gap-2">
                <VideoIcon className="size-5 shrink-0 text-muted" aria-hidden />
                <span className="truncate text-sm text-fg">{asset.name}</span>
              </div>
              <button type="button" onClick={clearUpload} className="inline-flex items-center gap-1 text-sm text-danger hover:underline">
                <CloseIcon className="size-4" aria-hidden />
                إزالة
              </button>
            </div>
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-caption text-muted">
              {asset.size ? <span>الحجم: {formatSize(asset.size)}</span> : null}
              {duration !== null ? <span>المدّة: {formatDuration(duration)}</span> : null}
              <span className={status === 'failed' ? 'text-danger' : status === 'ready' ? 'text-success' : 'text-warning'}>
                الحالة: {status ? (STATUS_LABEL[status] ?? status) : '—'}
              </span>
            </div>
            {status !== 'ready' && status !== 'failed' && (
              <div className="h-1 w-full overflow-hidden bg-surface-2">
                <div className="h-full w-1/3 animate-pulse bg-primary" />
              </div>
            )}
            <p className="text-caption text-muted">يمكنك الإرسال للمراجعة الآن؛ تكتمل المعالجة في الخلفية.</p>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => fileRef.current?.click()}
            disabled={uploading}
            aria-busy={uploading}
            className="flex h-28 flex-col items-center justify-center gap-1.5 border border-dashed border-border bg-surface text-sm text-muted transition-colors hover:border-primary hover:text-fg disabled:opacity-60"
          >
            <VideoIcon className="size-6" aria-hidden />
            {uploading ? 'جارٍ الرفع…' : (uploadLabel ?? defaultLabel)}
          </button>
        )
      ) : (
        <input
          type="url"
          inputMode="url"
          dir="ltr"
          value={url}
          onChange={(e) => {
            setUrl(e.target.value);
            onChange({ mediaAssetId: null, sourceUrl: e.target.value.trim() || null });
          }}
          placeholder="https://www.youtube.com/watch?v=…  أو رابط MP4 مباشر"
          className="w-full border border-border bg-surface px-3 py-2.5 text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30"
        />
      )}

      <input
        ref={fileRef}
        type="file"
        accept={VIDEO_ACCEPT.join(',')}
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) void uploadFile(f);
        }}
      />

      {allowLink && mode === 'link' && (
        <p className="text-caption text-muted">مدعوم: يوتيوب، فيميو، أو رابط MP4 مباشر.</p>
      )}
      {error && (
        <p role="alert" className="text-caption text-danger">
          {error}
        </p>
      )}
    </div>
  );
}
