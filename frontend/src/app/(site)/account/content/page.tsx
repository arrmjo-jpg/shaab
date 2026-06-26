import Link from 'next/link';

import { AccountTabs, type AccountTab } from '@/components/account/account-tabs';
import { ContentTable } from '@/components/account/content-table';
import { FileTextIcon, FilmIcon, PlusIcon, VideoIcon } from '@/components/icons';
import { buttonVariants } from '@/components/ui/button';
import { getMyContent, type ContentType } from '@/lib/account';
import { cn } from '@/lib/utils';

const CONTENT_TABS: AccountTab[] = [
  { key: 'articles', label: 'مقالات', icon: FileTextIcon },
  { key: 'videos', label: 'فيديوهات', icon: VideoIcon },
  { key: 'reels', label: 'ريلز', icon: FilmIcon },
];

const NEW: Record<ContentType, { type: string; label: string }> = {
  articles: { type: 'article', label: 'مقال' },
  videos: { type: 'video', label: 'فيديو' },
  reels: { type: 'reel', label: 'ريل' },
};

export default async function ContentPage({
  searchParams,
}: {
  searchParams: Promise<{ tab?: string; status?: string }>;
}) {
  const sp = await searchParams;
  const tab: ContentType = sp.tab === 'videos' || sp.tab === 'reels' ? sp.tab : 'articles';
  const status = typeof sp.status === 'string' && sp.status ? sp.status : 'all';
  const items = await getMyContent(tab, status);
  const create = NEW[tab];

  return (
    <div className="flex flex-col gap-5">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-heading text-h2 font-extrabold text-fg">محتواي</h1>
        <Link
          href={`/account/content/new?type=${create.type}`}
          className={cn(buttonVariants({ variant: 'primary', size: 'sm' }), 'gap-1.5')}
        >
          <PlusIcon className="size-4" aria-hidden />
          إنشاء {create.label}
        </Link>
      </div>
      <AccountTabs tabs={CONTENT_TABS} basePath="/account/content" active={tab} />
      <ContentTable items={items} type={tab} status={status} />
    </div>
  );
}
