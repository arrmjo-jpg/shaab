import { getLatestFeed, getMostReadFeed } from '@/lib/feed';

import { NewsTabs } from './news-tabs';

// ودجت الشريط الجانبيّ المشترك (المقال + الصفحات الثابتة + الأقسام) — تبويبان: «آخر الأخبار»
// و«الأكثر شيوعًا»، ١٠ لكلّ تبويب. غلاف خادميّ يجلب البيانات (إعادة استخدام getLatestFeed +
// getMostReadFeed — صفر API/نظام جديد)، ثمّ يمرّرها لمكوّن تبويبات client. فشل/فراغ ⇒ يُخفى.
export async function SidebarNewsWidget() {
  const [latestRaw, popular] = await Promise.all([getLatestFeed('ar'), getMostReadFeed('ar', 10)]);
  const latest = latestRaw.slice(0, 10);

  if (latest.length === 0 && popular.length === 0) return null;

  return <NewsTabs latest={latest} popular={popular} />;
}
