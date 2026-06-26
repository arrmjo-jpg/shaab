import { getStaticPages } from '@/lib/static-pages';

import { SubscribeBox } from './subscribe-box';

// غلاف خادميّ لصندوق الاشتراك — يجلب روابط الشروط/الخصوصية من صفحات الـCMS (نفس مصدر الفوتر،
// بلا روابط مُصلَّبة) ويمرّرها للجزيرة العميلة. صفحة مفقودة ⇒ يُسقَط سطر الشروط فقط (تدرّج لطيف).
export async function SubscribeBoxSection() {
  const pages = await getStaticPages('footer');
  const terms = pages.find((p) => /شروط|أحكام|terms/i.test(p.title)) ?? null;
  const privacy = pages.find((p) => /خصوص|privacy/i.test(p.title)) ?? null;

  return (
    <SubscribeBox
      termsHref={terms?.href ?? null}
      termsLabel={terms?.title ?? null}
      privacyHref={privacy?.href ?? null}
      privacyLabel={privacy?.title ?? null}
    />
  );
}
