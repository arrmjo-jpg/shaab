// روابط تنقّل صفحة الريلز — مصدر واحد يستعمله الشريط الجانبيّ (ديسكتوب) ودرج الجوّال.
export interface ReelsLink {
  label: string;
  href: string;
}

// التنقّل الأساسيّ.
export const REELS_PRIMARY: ReelsLink[] = [
  { label: 'الرئيسية', href: '/' },
  { label: 'الريلز', href: '/reels' },
  { label: 'آخر المستجدات', href: '/latest' },
];

// الخدمات والإضافات (بدل أقسام التصنيفات).
export const REELS_SERVICES: ReelsLink[] = [
  { label: 'الفيديوهات', href: '/videos' },
  { label: 'أسعار الذهب', href: '/gold-prices' },
  { label: 'الطقس', href: '/weather' },
  { label: 'بورصة عمّان', href: '/bourse' },
  { label: 'الرياضة', href: '/sport' },
  { label: 'الاقتصاد', href: '/economy' },
  { label: 'الأكثر رواجاً', href: '/trending' },
];
