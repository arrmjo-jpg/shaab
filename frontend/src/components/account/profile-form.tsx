'use client';

import { useId, useState, useTransition } from 'react';

import { Button } from '@/components/ui/button';
import { updateProfileAction } from '@/lib/account-actions';
import { cn } from '@/lib/utils';

const SOCIALS = [
  { key: 'facebook', label: 'فيسبوك', placeholder: 'https://facebook.com/…' },
  { key: 'x', label: 'إكس (تويتر)', placeholder: 'https://x.com/…' },
  { key: 'instagram', label: 'إنستغرام', placeholder: 'https://instagram.com/…' },
  { key: 'youtube', label: 'يوتيوب', placeholder: 'https://youtube.com/…' },
  { key: 'linkedin', label: 'لينكدإن', placeholder: 'https://linkedin.com/in/…' },
];

// Square fields (no border-radius) per design request.
const FIELD =
  'w-full border border-border bg-surface px-3 text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30';

export function ProfileForm({
  name,
  bio,
  social,
}: {
  name: string;
  bio: string;
  social: Record<string, string>;
}) {
  const [pending, startTransition] = useTransition();
  const [result, setResult] = useState<{ ok: boolean; message: string } | null>(null);
  const nameId = useId();
  const bioId = useId();

  function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setResult(null);
    const fd = new FormData(event.currentTarget);
    const socialLinks: Record<string, string> = {};
    for (const s of SOCIALS) {
      const v = String(fd.get(`social_${s.key}`) ?? '').trim();
      if (v) socialLinks[s.key] = v;
    }
    const payload = {
      name: String(fd.get('name') ?? '').trim(),
      bio: String(fd.get('bio') ?? '').trim(),
      social_links: socialLinks,
    };
    startTransition(async () => setResult(await updateProfileAction(payload)));
  }

  return (
    <form onSubmit={onSubmit} className="flex flex-col gap-4">
      {result && (
        <div
          role="status"
          className={cn(
            'border px-4 py-3 text-sm',
            result.ok ? 'border-success/30 bg-success/10 text-success' : 'border-danger/30 bg-danger/10 text-danger',
          )}
        >
          {result.message}
        </div>
      )}

      <div className="flex flex-col gap-1.5">
        <label htmlFor={nameId} className="text-sm font-medium text-fg">الاسم</label>
        <input id={nameId} name="name" type="text" required minLength={2} maxLength={100} defaultValue={name} className={`${FIELD} h-11`} />
      </div>

      <div className="flex flex-col gap-1.5">
        <label htmlFor={bioId} className="text-sm font-medium text-fg">النبذة</label>
        <textarea id={bioId} name="bio" rows={3} maxLength={1000} defaultValue={bio} placeholder="نبذة قصيرة عنك…" className={`${FIELD} py-2.5 leading-relaxed`} />
      </div>

      <fieldset className="flex flex-col gap-3">
        <legend className="mb-1 text-sm font-bold text-fg">روابط التواصل الاجتماعي</legend>
        {SOCIALS.map((s) => {
          const id = `social_${s.key}`;
          return (
            <div key={s.key} className="flex flex-col gap-1.5">
              <label htmlFor={id} className="text-caption text-muted">{s.label}</label>
              <input id={id} name={id} type="url" dir="ltr" defaultValue={social[s.key] ?? ''} placeholder={s.placeholder} className={`${FIELD} h-11 text-start`} />
            </div>
          );
        })}
      </fieldset>

      <div>
        <Button type="submit" variant="primary" size="md" disabled={pending} aria-busy={pending} className="rounded-none">
          {pending ? 'جارٍ الحفظ…' : 'حفظ التعديلات'}
        </Button>
      </div>
    </form>
  );
}
