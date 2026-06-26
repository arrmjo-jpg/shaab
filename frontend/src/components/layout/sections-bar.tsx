import Link from 'next/link';

import { Container } from './container';
import type { NavLink } from './nav-data';

// شريط أفقيّ لاصق تحت الهيدر (سكرول بلا شريط تمرير مرئيّ). عامّ — يُمرَّر `items` و`className` للتحكّم بالظهور المتجاوب:
// الموبايل = أقسام الموقع التحريريّة، سطح المكتب = روابط الوسائط (انظر (site)/layout).
export function SectionsBar({ items, className = '' }: { items: NavLink[]; className?: string }) {
  if (!items.length) return null;
  return (
    <div className={'sticky top-16 z-30 border-b border-border bg-surface/85 backdrop-blur-md ' + className}>
      <Container>
        <nav
          aria-label="أقسام الموقع"
          className="flex items-center gap-1 overflow-x-auto overflow-y-hidden py-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
          {items.map((s) => (
            <Link
              key={s.label}
              href={s.href}
              className="shrink-0 whitespace-nowrap px-3 py-1.5 text-sm font-medium text-fg transition-colors hover:text-primary"
            >
              {s.label}
            </Link>
          ))}
        </nav>
      </Container>
    </div>
  );
}
