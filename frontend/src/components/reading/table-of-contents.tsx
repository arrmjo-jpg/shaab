'use client';

import { useEffect, useState } from 'react';

import type { Heading } from '@/lib/reading';

// طبقة قراءة مشتركة — جدول محتوى لاصق مع تتبّع القسم النشط (scroll-spy). يستهلك العناوين
// المُستخرَجة خادميًّا (ids مستقرّة) — لا حقن DOM. يظهر فقط حين تكون العناوين ذات قيمة (≥2).
export function TableOfContents({ headings }: { headings: Heading[] }) {
  const [active, setActive] = useState<string>('');

  useEffect(() => {
    if (headings.length === 0) return;
    const els = headings
      .map((h) => document.getElementById(h.id))
      .filter((el): el is HTMLElement => el !== null);
    if (els.length === 0) return;

    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries.filter((e) => e.isIntersecting);
        if (visible.length === 0) return;
        const topmost = visible.reduce((a, b) =>
          a.boundingClientRect.top <= b.boundingClientRect.top ? a : b,
        );
        setActive(topmost.target.id);
      },
      { rootMargin: '-96px 0px -70% 0px', threshold: 0 },
    );

    els.forEach((el) => observer.observe(el));
    return () => observer.disconnect();
  }, [headings]);

  if (headings.length < 2) return null;

  return (
    <nav aria-label="على هذه الصفحة" className="text-sm">
      <p className="mb-3 font-bold text-fg">على هذه الصفحة</p>
      <ul className="flex flex-col gap-2 border-s border-border ps-3">
        {headings.map((h) => {
          const isActive = active === h.id;
          return (
            <li key={h.id} className={h.level === 3 ? 'ps-3' : ''}>
              <a
                href={`#${h.id}`}
                aria-current={isActive ? 'true' : undefined}
                className={
                  isActive
                    ? 'block font-medium text-primary'
                    : 'block text-muted transition-colors hover:text-fg'
                }
              >
                {h.text}
              </a>
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
