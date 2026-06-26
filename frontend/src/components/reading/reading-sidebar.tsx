import { AdZone } from '@/components/ads/ad-zone';

import { SidebarNewsWidget } from './sidebar-news-widget';

// الشريط الجانبيّ المشترك لصفحات القراءة (المقال + الصفحات الثابتة + الأقسام): إعلان حيّ
// (AdZone — client island، no-store) فوق ودجت الأخبار. لا إعلان ⇒ AdZone يعيد null فلا
// يُنشَأ عنصر فارغ ولا يتأثّر التخطيط. مصدر واحد للجانب (DRY) بدل تكراره في كلّ صفحة.
export function ReadingSidebar() {
  return (
    <div className="space-y-6">
      <AdZone zone="ads_in_side" />
      <SidebarNewsWidget />
    </div>
  );
}
