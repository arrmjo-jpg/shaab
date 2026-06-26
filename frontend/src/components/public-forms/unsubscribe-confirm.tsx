'use client';

import { useState } from 'react';

import { Button } from '@/components/ui/button';
import { getClientId } from '@/lib/client-id';

// تأكيد إلغاء الاشتراك — زرّ يطلب BFF (POST لتفادي الإلغاء العَرَضيّ عبر جلب الروابط المسبق).
export function UnsubscribeConfirm({ token }: { token: string }) {
  const [state, setState] = useState<'idle' | 'loading' | 'done'>('idle');
  const [message, setMessage] = useState<string | null>(null);
  const [error, setError] = useState<string | null>(null);

  async function onConfirm() {
    setState('loading');
    setError(null);
    try {
      const res = await fetch('/api/whatsapp/unsubscribe', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Client-Id': getClientId() },
        body: JSON.stringify({ token }),
      });
      const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));
      if (!res.ok || data.success === false) {
        setError(data.message || 'تعذّر إلغاء الاشتراك، يرجى المحاولة لاحقاً.');
        setState('idle');
        return;
      }
      setMessage(data.message || 'تم إلغاء اشتراكك.');
      setState('done');
    } catch {
      setError('حدث خطأ في الاتصال، يرجى المحاولة لاحقاً.');
      setState('idle');
    }
  }

  if (state === 'done') {
    return (
      <div role="status" className="border border-success/30 bg-success/10 px-4 py-4 text-sm text-success">
        {message}
      </div>
    );
  }

  return (
    <div className="flex flex-col gap-4">
      {error && (
        <div role="alert" className="border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
          {error}
        </div>
      )}
      <p className="text-fg">هل تريد إلغاء اشتراكك في رسائل واتساب؟ لن تصلك رسائل بعد ذلك.</p>
      <Button type="button" variant="primary" size="lg" disabled={state === 'loading'} aria-busy={state === 'loading'} className="rounded-none sm:w-auto" onClick={() => void onConfirm()}>
        {state === 'loading' ? 'جارٍ الإلغاء…' : 'تأكيد إلغاء الاشتراك'}
      </Button>
    </div>
  );
}
