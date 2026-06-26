'use client';

import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { Clapperboard, CloudSun, Home, Trophy, Video } from 'lucide-react';

// شريط تنقّل سفليّ ثابت للموبايل (نمط تطبيقات الجوّال). يظهر < lg فقط. النشط مُبرَز.
// التبويبات بطلب المستخدم: الرئيسية · الريلز · الفيديوهات · الرياضة · الطقس.
const TABS: { label: string; href: string; Icon: typeof Home }[] = [
  { label: 'الرئيسية', href: '/', Icon: Home },
  { label: 'الريلز', href: '/reels', Icon: Clapperboard },
  { label: 'الفيديوهات', href: '/videos', Icon: Video },
  { label: 'الرياضة', href: '/sport', Icon: Trophy },
  { label: 'الطقس', href: '/weather', Icon: CloudSun },
];

export function MobileBottomNav() {
  const pathname = usePathname();
  return (
    <nav
      dir="rtl"
      aria-label="تنقّل سفليّ"
      className="fixed inset-x-0 bottom-0 z-40 border-t border-white/15 bg-primary text-primary-foreground lg:hidden"
      style={{ paddingBottom: 'env(safe-area-inset-bottom)' }}
    >
      <ul className="mx-auto flex max-w-[480px] items-stretch justify-around">
        {TABS.map(({ label, href, Icon }) => {
          const active = href === '/' ? pathname === '/' : pathname.startsWith(href);
          return (
            <li key={href} className="flex-1">
              <Link
                href={href}
                aria-current={active ? 'page' : undefined}
                className={
                  'flex flex-col items-center gap-1 px-1 py-2 text-[11px] font-bold transition-opacity ' +
                  (active ? 'opacity-100' : 'opacity-70 hover:opacity-100')
                }
              >
                <Icon className="size-5 shrink-0" aria-hidden />
                {label}
              </Link>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
