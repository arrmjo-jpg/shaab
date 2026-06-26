'use client';

import { useId, useRef, useState } from 'react';

import { CloseIcon, ImagePlusIcon } from '@/components/icons';

export interface UploadedImage {
  id: number;
  url: string;
}

// يطابق سقف الإدارة (config performance.media.image_max_kb = 5120) + صيغها.
const ACCEPT = ['image/jpeg', 'image/png', 'image/webp'];
const MAX_BYTES = 5 * 1024 * 1024;

/**
 * حقل صورة يرفع عبر طبقة ملكيّة وسائط الكاتب فقط (BFF /api/media → POST /api/v1/media)،
 * ثمّ يُعيد معرّف الأصل (id) للنموذج ليربطه (media[cover] / og_image_id). لا منطق رفع
 * جديد: الرفع والمعالجة والملكيّة كلّها في الخادم. الفحص هنا للـUX فقط (الخادم هو الحَكَم).
 */
export function MediaImageField({
  label,
  hint,
  value,
  onChange,
}: {
  label: string;
  hint?: string;
  value: UploadedImage | null;
  onChange: (asset: UploadedImage | null) => void;
}) {
  const inputRef = useRef<HTMLInputElement>(null);
  const fieldId = useId();
  const [uploading, setUploading] = useState(false);
  const [error, setError] = useState<string | null>(null);

  async function handleFile(file: File) {
    setError(null);
    if (!ACCEPT.includes(file.type)) {
      setError('صيغة غير مدعومة (JPEG أو PNG أو WebP فقط).');
      return;
    }
    if (file.size > MAX_BYTES) {
      setError('حجم الصورة يتجاوز 5 ميغابايت.');
      return;
    }

    setUploading(true);
    try {
      const fd = new FormData();
      fd.append('file', file);
      const res = await fetch('/api/media', { method: 'POST', body: fd });
      const data: {
        success?: boolean;
        message?: string;
        asset?: { id?: number; url?: string; thumb?: string | null };
      } = await res.json().catch(() => ({}));

      if (!res.ok || !data.success || !data.asset?.id) {
        setError(data.message || 'تعذّر رفع الصورة.');
        return;
      }
      onChange({ id: data.asset.id, url: data.asset.thumb || data.asset.url || '' });
    } catch {
      setError('تعذّر الاتصال بالخادم.');
    } finally {
      setUploading(false);
    }
  }

  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-sm font-medium text-fg">{label}</span>

      {value ? (
        <div className="flex items-center gap-3 border border-border bg-surface p-2">
          {/* eslint-disable-next-line @next/next/no-img-element -- raw <img> until the unified Image-Platform slice */}
          <img src={value.url} alt="" className="size-16 shrink-0 object-cover" />
          <button
            type="button"
            onClick={() => {
              onChange(null);
              if (inputRef.current) inputRef.current.value = '';
            }}
            className="inline-flex items-center gap-1 text-sm text-danger hover:underline"
          >
            <CloseIcon className="size-4" aria-hidden />
            إزالة
          </button>
        </div>
      ) : (
        <button
          type="button"
          onClick={() => inputRef.current?.click()}
          disabled={uploading}
          aria-busy={uploading}
          className="flex h-24 flex-col items-center justify-center gap-1.5 border border-dashed border-border bg-surface text-sm text-muted transition-colors hover:border-primary hover:text-fg disabled:opacity-60"
        >
          <ImagePlusIcon className="size-5" aria-hidden />
          {uploading ? 'جارٍ الرفع…' : 'اختيار صورة'}
        </button>
      )}

      <input
        ref={inputRef}
        id={fieldId}
        type="file"
        accept={ACCEPT.join(',')}
        className="hidden"
        onChange={(e) => {
          const f = e.target.files?.[0];
          if (f) void handleFile(f);
        }}
      />

      {hint && !error && <p className="text-caption text-muted">{hint}</p>}
      {error && (
        <p role="alert" className="text-caption text-danger">
          {error}
        </p>
      )}
    </div>
  );
}
