import { Film } from 'lucide-react';

// حالة فارغة فاخرة (مُقدِّميّة) — أيقونة + عنوان + رسالة. تُعرَض فقط حين لا محتوى حقيقيّ إطلاقاً (لا تلفيق/placeholder).
// مربّعة، tokens. النصوص props بقيم افتراضيّة (لا hardcoding لمحتوى).
export function VideoEmptyState({
  title = 'لا توجد فيديوهات حالياً',
  message = 'سيظهر هنا أحدث المحتوى المرئيّ فور توفّره.',
}: {
  title?: string;
  message?: string;
}) {
  return (
    <div className="flex flex-col items-center justify-center gap-3 border border-dashed border-border bg-surface-2 px-6 py-24 text-center">
      <span className="flex size-14 items-center justify-center bg-surface-3 text-muted" aria-hidden>
        <Film className="size-7" />
      </span>
      <h2 className="font-heading text-h3 font-bold text-fg">{title}</h2>
      <p className="max-w-md text-sm text-muted">{message}</p>
    </div>
  );
}
