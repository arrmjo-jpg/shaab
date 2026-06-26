import Link from 'next/link';

import { CreateArticleForm } from '@/components/account/create-article-form';
import { CreateReelForm } from '@/components/account/create-reel-form';
import { CreateVideoForm } from '@/components/account/create-video-form';
import { buttonVariants } from '@/components/ui/button';
import { requireUser } from '@/lib/auth';
import { getArticleCategories } from '@/lib/categories';
import { cn } from '@/lib/utils';

const TITLES: Record<string, string> = {
  article: 'إنشاء مقال أو خبر',
  news: 'إنشاء مقال أو خبر',
  video: 'إنشاء فيديو',
  reel: 'إنشاء ريل',
};

export default async function NewContentPage({
  searchParams,
}: {
  searchParams: Promise<{ type?: string }>;
}) {
  const user = await requireUser();
  const sp = await searchParams;
  const type =
    sp.type === 'video' || sp.type === 'reel' || sp.type === 'news' ? sp.type : 'article';
  const isArticle = type === 'article' || type === 'news';
  const isVideo = type === 'video';

  // Creating content is writer-only (backend enforces the `writer` ability too).
  if (!user.is_writer) {
    return (
      <div className="mx-auto max-w-2xl">
        <div className="border border-border bg-surface p-6 text-center">
          <h1 className="font-heading text-h3 font-bold text-fg">الإنشاء متاح للكتّاب</h1>
          <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-muted">
            يجب أن تكون كاتباً لإنشاء محتوى. يمكنك تقديم طلب الترقية إلى كاتب من صفحة ملفك الشخصيّ.
          </p>
          <Link href="/account/profile" className={cn(buttonVariants({ variant: 'primary', size: 'md' }), 'mt-4')}>
            طلب الترقية إلى كاتب
          </Link>
        </div>
      </div>
    );
  }

  // أقسام المقال/الخبر فقط (الفيديو بلا تصنيف في نموذج الكاتب — المحرّر يكمله).
  const [newsCategories, opinionCategories] = isArticle
    ? await Promise.all([getArticleCategories('news'), getArticleCategories('opinion')])
    : [[], []];

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <h1 className="font-heading text-h2 font-extrabold text-fg">{TITLES[type]}</h1>
      <section className="border border-border bg-surface p-5 sm:p-6">
        {isArticle ? (
          <CreateArticleForm newsCategories={newsCategories} opinionCategories={opinionCategories} />
        ) : isVideo ? (
          <CreateVideoForm />
        ) : (
          <CreateReelForm />
        )}
      </section>
    </div>
  );
}
