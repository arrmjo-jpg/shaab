// Static placeholder content for the Phase-1 shell — header nav, breaking bar, and footer
// category/media columns (placeholders pending the Categories/Media APIs).
// Footer STATIC PAGES come from the CMS (site-footer.tsx + lib/static-pages.ts), not from here.

export type NavLink = { label: string; href: string };

export const MAIN_NAV: NavLink[] = [
  { label: 'أخبار', href: '#' },
  { label: 'سياسة', href: '#' },
  { label: 'اقتصاد', href: '#' },
  { label: 'رياضة', href: '#' },
  { label: 'فيديو', href: '#' },
  { label: 'ريلز', href: '#' },
  { label: 'رأي', href: '#' },
];

export const MORE_NAV: NavLink[] = [
  { label: 'ثقافة وفنون', href: '#' },
  { label: 'تكنولوجيا', href: '#' },
  { label: 'صحة', href: '#' },
  { label: 'منوعات', href: '#' },
  { label: 'الجريدة الرقمية', href: '/epaper' },
];

// روابط الوسائط/الخدمات — على الموبايل في القائمة الجانبيّة (الهامبرغر)؛ على سطح المكتب في الشريط الأفقيّ تحت الهيدر.
export const SECTIONS_NAV: NavLink[] = [
  { label: 'فيديوهات', href: '/videos' },
  { label: 'الريلز', href: '/reels' },
  { label: 'البث المباشر', href: '/live' },
  { label: 'جدول الرياضة', href: '/sport' },
  { label: 'بورصة عمّان', href: '/bourse' },
  { label: 'اسعار الذهب', href: '/gold-prices' },
  { label: 'حالة الطقس', href: '/weather' },
];

// أقسام الموقع التحريريّة — على الموبايل في الشريط الأفقيّ تحت الهيدر (بلا الوسائط فيديو/ريلز وبلا الجريدة الرقمية).
export const MOBILE_SECTION_NAV: NavLink[] = [
  ...MAIN_NAV.filter((l) => l.label !== 'فيديو' && l.label !== 'ريلز'),
  ...MORE_NAV.filter((l) => l.label !== 'الجريدة الرقمية'),
];

export const BREAKING: string[] = [
  'عنوان خبر عاجل تجريبيّ يوضّح شكل شريط الأخبار العاجلة في المنصّة',
  'خبر عاجل ثانٍ — محتوى تجريبيّ (Placeholder) لا يأتي من الباك إند',
  'خبر عاجل ثالث لعرض سلوك التمرير الأفقيّ داخل الشريط',
];

// Footer category/media columns — placeholders until wired to the Categories/Media APIs.
export const FOOTER_SECTIONS: { title: string; links: string[] }[] = [
  { title: 'الأقسام', links: ['أخبار', 'سياسة', 'اقتصاد', 'رياضة', 'رأي', 'ثقافة وفنون'] },
  { title: 'الوسائط', links: ['فيديو', 'ريلز', 'الجريدة الرقمية', 'بودكاست', 'بثّ مباشر'] },
];

// Platform links not (yet) modeled as CMS pages — placeholders pending Authors/Careers wiring.
export const PLATFORM_LINKS: NavLink[] = [
  { label: 'الكتّاب', href: '#' },
  { label: 'فرص العمل', href: '#' },
  { label: 'اتصل بنا', href: '/contact' },
  { label: 'أعلن معنا', href: '/advertise' },
];
