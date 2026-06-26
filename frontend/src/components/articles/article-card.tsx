import Link from 'next/link';

import type { FeedItem } from '@/lib/feed';

// بطاقة مقال مفردة (مُستخرَجة من قوائم الهوم — نفس الشكل تماماً: صورة 16:9 + عنوان، رابط متراكب).
// **مصدر واحد** يُعاد استخدامه في أقسام الهوم وصفحات نشاط الحساب (أعجبني/المحفوظات) — لا بطاقة
// جديدة، لا تكرار منطق. تأخذ view-model الموجود `FeedItem`.
export function ArticleCard({ item }: { item: FeedItem }) {
  return (
    <article className="group relative">
      <Link href={item.href} className="absolute inset-0 z-10" aria-label={item.title} />
      <div className="overflow-hidden bg-surface-2">
        {item.image ? (
          // eslint-disable-next-line @next/next/no-img-element -- <img> مقصود: حارس أداء الهوم
          <img
            src={item.image}
            alt={item.imageAlt}
            loading="lazy"
            decoding="async"
            className="aspect-[16/9] w-full object-cover transition-transform duration-500 ease-out group-hover:scale-105 motion-reduce:group-hover:scale-100"
          />
        ) : (
          <div className="aspect-[16/9] w-full bg-surface-3" aria-hidden />
        )}
      </div>
      <h4 className="mt-2 line-clamp-2 text-sm font-bold leading-snug text-fg transition-colors group-hover:text-primary sm:text-[15px]">
        {item.title}
      </h4>
    </article>
  );
}
