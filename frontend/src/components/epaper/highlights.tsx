import type { HighlightItem } from '@/lib/epaper';

// أبرز ما في العدد — «اختارها المحرِّر». قسم تحريريّ اختياريّ: يظهر فقط عند وجود مختارات
// منتقاة؛ فارغ ⇒ لا يُعرَض (لا صندوق فارغ للقارئ، ولا تلفيق).
export function Highlights({ items }: { items: HighlightItem[] }) {
  if (items.length === 0) return null;

  return (
    <section className="mt-10" dir="rtl" aria-labelledby="epaper-highlights-heading">
      <div className="flex items-center gap-3 border-b border-border pb-3">
        <span className="h-6 w-1.5 shrink-0 bg-primary" aria-hidden />
        <h2 id="epaper-highlights-heading" className="text-xl font-extrabold text-fg">
          أبرز ما في العدد
        </h2>
      </div>

      <div className="mt-4 grid gap-4 sm:grid-cols-3">
        {items.map((it, i) => (
          <article key={i} className="border border-border p-4">
            <h3 className="text-base font-bold leading-snug text-fg">{it.title}</h3>
            {it.quote ? <p className="mt-2 text-sm text-muted">{it.quote}</p> : null}
            {it.page ? <span className="mt-3 block text-xs text-muted">صفحة {it.page}</span> : null}
          </article>
        ))}
      </div>
    </section>
  );
}
