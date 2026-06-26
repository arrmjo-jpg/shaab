'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Cookie, X } from 'lucide-react';

// زرّ «سياسة الكوكيز» + مودال احترافيّ — النصّ من إعدادات الموقع (cookie_policy_text، نصّ خام
// يُعرض بـ whitespace-pre-line لا HTML). إغلاق بـ ESC/الخلفيّة/زرّ X + قفل تمرير الصفحة + تركيز
// تلقائيّ. نصّ فارغ ⇒ null (لا زرّ ولا مودال — صفر تلفيق). Portal فوق كلّ شيء (نمط مودال الريلز).
//
// وضعان: (أ) زرّ يدويّ (الفوتر). (ب) `autoOpenKey` + `hideTrigger` = نسخة موافقة تلقائيّة تُفتح
// وسط الشاشة عند أوّل زيارة فقط — أيّ إغلاق يُسجَّل في localStorage فلا تظهر مرّة أخرى.
export function CookiePolicyModal({
  text,
  className,
  autoOpenKey,
  hideTrigger = false,
}: {
  text: string;
  className?: string;
  /** مفتاح localStorage — حين يُمرَّر: فتح تلقائيّ عند أوّل زيارة، والإغلاق يُذكَر دائمًا. */
  autoOpenKey?: string;
  /** بلا زرّ (نسخة الفتح التلقائيّ في الـlayout). */
  hideTrigger?: boolean;
}) {
  const [open, setOpen] = useState(false);
  const closeRef = useRef<HTMLButtonElement | null>(null);

  const close = useCallback(() => {
    setOpen(false);
    if (autoOpenKey) {
      try {
        window.localStorage.setItem(autoOpenKey, '1');
      } catch {
        /* تخزين محظور (متصفّح خاصّ) — تظهر بالجلسة التالية، لا كسر */
      }
    }
  }, [autoOpenKey]);

  // فتح تلقائيّ بعد الترطيب — مرّة واحدة فقط (ما لم يسبق الإغلاق).
  useEffect(() => {
    if (!autoOpenKey || !text.trim()) return;
    try {
      if (!window.localStorage.getItem(autoOpenKey)) setOpen(true);
    } catch {
      /* تخزين محظور ⇒ لا فتح تلقائيّ (أفضل من إزعاج بكلّ صفحة) */
    }
  }, [autoOpenKey, text]);

  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === 'Escape') close();
    };
    document.addEventListener('keydown', onKey);
    const prev = document.body.style.overflow;
    document.body.style.overflow = 'hidden';
    closeRef.current?.focus();
    return () => {
      document.removeEventListener('keydown', onKey);
      document.body.style.overflow = prev;
    };
  }, [open, close]);

  const policy = text.trim();
  if (!policy) return null;

  return (
    <>
      {hideTrigger ? null : (
        <button type="button" onClick={() => setOpen(true)} className={className}>
          سياسة الكوكيز
        </button>
      )}

      {open
        ? createPortal(
            <div
              className="fixed inset-0 z-[100] flex items-center justify-center p-4"
              role="dialog"
              aria-modal="true"
              aria-labelledby="cookie-policy-title"
            >
              {/* الخلفيّة — نقرة تغلق */}
              <button
                type="button"
                aria-label="إغلاق"
                onClick={close}
                className="absolute inset-0 cursor-default bg-black/60 backdrop-blur-sm"
              />

              {/* اللوحة */}
              <div
                className="relative flex max-h-[80vh] w-full max-w-lg flex-col overflow-hidden bg-surface text-fg shadow-2xl"
                style={{ borderRadius: '14px' }}
              >
                <div className="flex items-center justify-between gap-3 border-b border-border px-5 py-4">
                  <div className="flex items-center gap-2.5">
                    <span
                      className="inline-flex size-9 items-center justify-center bg-primary/10 text-primary"
                      style={{ borderRadius: '10px' }}
                    >
                      <Cookie className="size-5" aria-hidden />
                    </span>
                    <h2 id="cookie-policy-title" className="font-heading text-base font-extrabold">
                      سياسة الكوكيز
                    </h2>
                  </div>
                  <button
                    ref={closeRef}
                    type="button"
                    onClick={close}
                    aria-label="إغلاق"
                    className="inline-flex size-8 shrink-0 items-center justify-center bg-surface-2 text-muted transition-colors hover:bg-surface-3 hover:text-fg"
                    style={{ borderRadius: '8px' }}
                  >
                    <X className="size-4" aria-hidden />
                  </button>
                </div>

                <div className="overflow-y-auto px-5 py-4">
                  <p className="whitespace-pre-line text-sm leading-7 text-fg">{policy}</p>
                </div>

                <div className="border-t border-border px-5 py-3 text-end">
                  <button
                    type="button"
                    onClick={close}
                    className="inline-flex items-center bg-primary px-4 py-2 text-sm font-bold text-white transition-colors hover:bg-primary/90"
                    style={{ borderRadius: '10px' }}
                  >
                    حسنًا، فهمت
                  </button>
                </div>
              </div>
            </div>,
            document.body,
          )
        : null}
    </>
  );
}
