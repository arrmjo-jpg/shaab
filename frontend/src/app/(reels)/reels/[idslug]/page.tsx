import type { Metadata } from 'next';
import { notFound } from 'next/navigation';

import { ReelsAccountBlock, ReelsSocialRow } from '@/components/reels/reels-extras';
import { ReelsFeed, type ReelsNavItem } from '@/components/reels/reels-feed';
import { socialEntries } from '@/components/layout/social-map';
import { getCurrentUser } from '@/lib/auth';
import { getReelByIdSlug, getReelsFeed } from '@/lib/reels';
import { REELS_PRIMARY, REELS_SERVICES } from '@/lib/reels-nav';
import { getSiteSettings } from '@/lib/site-settings';

// رابط عميق لريل محدّد — يفتح الـfeed مبتدئاً به. ISR = سقف أمان؛ التحديث حدثيّ عبر reel:{locale}:{slug}.
export const revalidate = 21600;

export async function generateMetadata({
  params,
}: {
  params: Promise<{ idslug: string }>;
}): Promise<Metadata> {
  const { idslug } = await params;
  const reel = await getReelByIdSlug(idslug);
  if (!reel) return { title: 'الريلز' };
  return {
    title: reel.title,
    description: reel.description ?? undefined,
    openGraph: {
      type: 'video.other',
      title: reel.title,
      description: reel.description ?? undefined,
      images: reel.poster ? [reel.poster] : undefined,
    },
  };
}

export default async function ReelDeepLinkPage({ params }: { params: Promise<{ idslug: string }> }) {
  const { idslug } = await params;
  const [reel, page, settings, user] = await Promise.all([
    getReelByIdSlug(idslug),
    getReelsFeed(),
    getSiteSettings(),
    getCurrentUser(),
  ]);
  if (!reel) notFound();

  // الريل المطلوب أوّلاً ثمّ بقيّة الخلاصة (بلا تكرار).
  const items = [reel, ...page.items.filter((i) => i.id !== reel.id)];
  const navItems: ReelsNavItem[] = [
    ...REELS_PRIMARY.map((l) => ({ ...l, active: l.href === '/reels' })),
    ...REELS_SERVICES,
  ];
  const extras = (
    <>
      <ReelsAccountBlock user={user} />
      <ReelsSocialRow social={socialEntries(settings?.social)} />
    </>
  );

  return (
    <ReelsFeed
      initialItems={items}
      initialCursor={page.nextCursor}
      startId={reel.id}
      siteName={settings?.site_name || 'صدى الشعب الأخباري'}
      logo={settings?.logo_dark ?? settings?.logo_light ?? null}
      navItems={navItems}
      extrasSlot={extras}
    />
  );
}
