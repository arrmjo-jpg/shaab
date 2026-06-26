'use client';

import { useState, type FormEvent } from 'react';

// نموذج التعليق — **زائر**: الاسم + البريد + النصّ؛ **مسجّل دخول**: النصّ فقط (الاسم/البريد من الحساب خادميّاً).
// يرسل لـBFF /api/comments (يمرّره للباك إند الذي يُنشئ pending). نجاح ⇒ إشعار «قيد المراجعة» (لا إدراج متفائل — التعليق معلَّق).
export function CommentForm({ slug, isLoggedIn }: { slug: string; isLoggedIn: boolean }) {
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [body, setBody] = useState('');
  const [status, setStatus] = useState<'idle' | 'submitting' | 'success' | 'error'>('idle');
  const [error, setError] = useState('');

  async function onSubmit(e: FormEvent) {
    e.preventDefault();
    if (body.trim().length < 2) return;
    setStatus('submitting');
    setError('');
    try {
      const payload: Record<string, unknown> = { slug, body: body.trim() };
      if (!isLoggedIn) {
        payload.authorName = name.trim();
        payload.authorEmail = email.trim();
      }
      const res = await fetch('/api/comments', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
      });
      if (res.ok) {
        setStatus('success');
        setBody('');
        if (!isLoggedIn) {
          setName('');
          setEmail('');
        }
      } else {
        const j = (await res.json().catch(() => null)) as { message?: string } | null;
        setError(j?.message || 'تعذّر إرسال التعليق. حاول مرّةً أخرى.');
        setStatus('error');
      }
    } catch {
      setError('تعذّر الاتصال. تحقّق من الشبكة وحاول مجدّداً.');
      setStatus('error');
    }
  }

  if (status === 'success') {
    return (
      <div className="border border-success/40 bg-success/5 p-4 text-sm text-fg">
        شكراً لك — استُلم تعليقك وهو <strong className="font-bold">قيد المراجعة</strong> قبل النشر.
        <button type="button" onClick={() => setStatus('idle')} className="ms-2 font-bold text-primary hover:underline">
          إضافة تعليق آخر
        </button>
      </div>
    );
  }

  const field = 'w-full border border-border bg-surface-2 px-3 py-2 text-sm text-fg outline-none transition-colors focus:border-primary';

  return (
    <form onSubmit={onSubmit} className="space-y-3">
      {!isLoggedIn && (
        <div className="grid gap-3 sm:grid-cols-2">
          <input value={name} onChange={(e) => setName(e.target.value)} required maxLength={120} placeholder="الاسم" aria-label="الاسم" className={field} />
          <input value={email} onChange={(e) => setEmail(e.target.value)} required type="email" maxLength={190} placeholder="البريد الإلكتروني" aria-label="البريد الإلكتروني" className={field} />
        </div>
      )}
      <textarea
        value={body}
        onChange={(e) => setBody(e.target.value)}
        required
        minLength={2}
        maxLength={5000}
        rows={4}
        placeholder="اكتب تعليقك…"
        aria-label="نصّ التعليق"
        className={`${field} resize-y`}
      />
      {status === 'error' && <p className="text-sm text-danger">{error}</p>}
      <div className="flex flex-wrap items-center gap-3">
        <button
          type="submit"
          disabled={status === 'submitting'}
          className="bg-primary px-5 py-2 text-sm font-bold text-primary-foreground transition-opacity hover:opacity-90 disabled:opacity-60"
        >
          {status === 'submitting' ? 'جارٍ الإرسال…' : 'إرسال التعليق'}
        </button>
        <span className="text-caption text-muted">تُنشر التعليقات بعد المراجعة.</span>
      </div>
    </form>
  );
}
