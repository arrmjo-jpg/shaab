'use client';

import { useState, useTransition } from 'react';

import { Button } from '@/components/ui/button';
import { requestWriterUpgradeAction } from '@/lib/account-actions';

// Writer-upgrade request (square design). Hidden for writers (parent gates on is_writer).
export function WriterRequestCard({ status }: { status: string | null }) {
  const [pending, startTransition] = useTransition();
  const [result, setResult] = useState<{ ok: boolean; message: string } | null>(null);

  if (status === 'pending') {
    return (
      <div className="border border-warning/30 bg-warning/10 p-5">
        <h3 className="font-heading text-base font-bold text-fg">طلب الترقية قيد المراجعة</h3>
        <p className="mt-1 text-sm leading-relaxed text-muted">
          تلقّينا طلبك لتصبح كاتباً، وهو الآن قيد المراجعة. سنُعلمك بالنتيجة عبر الإشعارات.
        </p>
      </div>
    );
  }

  if (result?.ok) {
    return (
      <div className="border border-success/30 bg-success/10 p-5">
        <h3 className="font-heading text-base font-bold text-fg">تمّ إرسال الطلب</h3>
        <p className="mt-1 text-sm leading-relaxed text-muted">{result.message}</p>
      </div>
    );
  }

  function onSubmit(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    setResult(null);
    const note = String(new FormData(event.currentTarget).get('note') ?? '').trim();
    startTransition(async () => setResult(await requestWriterUpgradeAction(note)));
  }

  return (
    <div className="border border-border bg-surface p-5">
      <h3 className="font-heading text-base font-bold text-fg">الترقية إلى كاتب</h3>
      <p className="mt-1 text-sm leading-relaxed text-muted">
        انضمّ إلى فريق الكتّاب لنشر مقالاتك وفيديوهاتك وريلزك على المنصّة.
      </p>
      {status === 'rejected' && (
        <p className="mt-2 text-caption text-warning">طلبك السابق لم يُقبَل — يمكنك إعادة التقديم.</p>
      )}

      <form onSubmit={onSubmit} className="mt-4 flex flex-col gap-3">
        {result && !result.ok && (
          <div role="alert" className="border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
            {result.message}
          </div>
        )}
        <textarea
          name="note"
          rows={3}
          maxLength={1000}
          placeholder="لماذا تودّ أن تصبح كاتباً؟ (اختياري)"
          className="w-full border border-border bg-surface px-3 py-2.5 text-sm leading-relaxed text-fg outline-none transition-colors placeholder:text-muted focus-visible:border-primary focus-visible:ring-2 focus-visible:ring-primary/30"
        />
        <div>
          <Button type="submit" variant="primary" size="md" disabled={pending} aria-busy={pending} className="rounded-none">
            {pending ? 'جارٍ الإرسال…' : 'إرسال طلب الترقية'}
          </Button>
        </div>
      </form>
    </div>
  );
}
