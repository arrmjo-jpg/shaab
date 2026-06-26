'use client';

import { useRouter } from 'next/navigation';
import { useId, useState, useTransition } from 'react';

import { VideoSourceField, type VideoSource } from '@/components/account/video-source-field';
import { Button } from '@/components/ui/button';
import { createReelAction } from '@/lib/account-actions';

const FIELD =
  'w-full border border-border bg-surface px-3 text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30';

const REEL_MAX = 150 * 1024 * 1024; // 150MB — نفس حدّ الريل في الإدارة

export function CreateReelForm() {
  const router = useRouter();
  const [pending, startTransition] = useTransition();
  const [error, setError] = useState<string | null>(null);
  const [source, setSource] = useState<VideoSource>({ mediaAssetId: null, sourceUrl: null });

  const titleId = useId();
  const descId = useId();

  function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setError(null);
    const fd = new FormData(event.currentTarget);
    const title = String(fd.get('title') ?? '').trim();
    const description = String(fd.get('description') ?? '').trim();

    if (title.length < 2) {
      setError('يرجى كتابة عنوان الريل.');
      return;
    }
    if (!source.mediaAssetId) {
      setError('يرجى رفع فيديو الريل.');
      return;
    }

    startTransition(async () => {
      const r = await createReelAction({ title, description, mediaAssetId: source.mediaAssetId });
      if (!r.ok) {
        setError(r.message);
        return;
      }
      router.push('/account/content?tab=reels');
    });
  }

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-5">
      {error && (
        <div role="alert" className="border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}

      {/* العنوان */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor={titleId} className="text-sm font-medium text-fg">
          العنوان <span className="text-danger">*</span>
        </label>
        <input id={titleId} name="title" type="text" required minLength={2} maxLength={200} className={`${FIELD} h-11`} />
      </div>

      {/* فيديو الريل — رفع فقط (ملف معالجة reel: مصغّرة + نسخ) */}
      <VideoSourceField
        onChange={setSource}
        profile="reel"
        allowLink={false}
        maxBytes={REEL_MAX}
        uploadLabel="اختر فيديو الريل (عموديّ، MP4/WebM، حتى 150 م.ب)"
      />

      {/* الوصف */}
      <div className="flex flex-col gap-1.5">
        <label htmlFor={descId} className="text-sm font-medium text-fg">
          الوصف (اختياري)
        </label>
        <textarea id={descId} name="description" rows={4} maxLength={5000} className={`${FIELD} py-2.5 leading-relaxed`} />
      </div>

      <div className="flex items-center gap-3">
        <Button type="submit" variant="primary" size="md" disabled={pending} aria-busy={pending}>
          {pending ? 'جارٍ الإرسال…' : 'إرسال للمراجعة'}
        </Button>
        <p className="text-caption text-muted">يُرسَل مباشرةً للمراجعة (تكتمل معالجة الريل في الخلفية).</p>
      </div>
    </form>
  );
}
