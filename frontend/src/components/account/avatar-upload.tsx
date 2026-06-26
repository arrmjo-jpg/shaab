'use client';

import { useRouter } from 'next/navigation';
import { useRef, useState, useTransition } from 'react';

import { Button } from '@/components/ui/button';

const MAX_BYTES = 5 * 1024 * 1024;

export function AvatarUpload({ avatar, name }: { avatar: string | null; name: string }) {
  const router = useRouter();
  const inputRef = useRef<HTMLInputElement>(null);
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [preview, setPreview] = useState<string | null>(avatar);

  function onChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0];
    if (!file) return;
    setError(null);

    if (file.size > MAX_BYTES) {
      setError('الحجم الأقصى للصورة 5 ميغابايت.');
      return;
    }

    setPreview(URL.createObjectURL(file));
    const fd = new FormData();
    fd.append('avatar', file);

    startTransition(async () => {
      try {
        const res = await fetch('/api/auth/avatar', { method: 'POST', body: fd });
        const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));
        if (!res.ok || data.success === false) {
          setError(data.message || 'تعذّر رفع الصورة.');
          setPreview(avatar);
          return;
        }
        router.refresh();
      } catch {
        setError('حدث خطأ في الاتصال.');
        setPreview(avatar);
      }
    });
  }

  const initial = name?.trim().charAt(0) || '؟';

  return (
    <div className="flex items-center gap-4">
      <div className="avatar flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface-2 text-fg">
        {preview ? (
          // eslint-disable-next-line @next/next/no-img-element -- raw <img> until the unified Image-Platform slice
          <img src={preview} alt={name} className="size-full object-cover" />
        ) : (
          <span className="font-heading text-2xl font-bold">{initial}</span>
        )}
      </div>
      <div>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => inputRef.current?.click()}
          disabled={pending}
        >
          {pending ? 'جارٍ الرفع…' : 'تغيير الصورة'}
        </Button>
        <p className="mt-1.5 text-caption text-muted">JPG أو PNG أو WebP — حتى 5 ميغابايت.</p>
        {error && <p className="mt-1 text-caption text-danger">{error}</p>}
      </div>
      <input
        ref={inputRef}
        type="file"
        accept="image/jpeg,image/png,image/webp"
        onChange={onChange}
        className="hidden"
      />
    </div>
  );
}
