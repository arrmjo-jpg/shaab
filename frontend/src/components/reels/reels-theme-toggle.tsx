'use client';

import { useEffect, useState } from 'react';

// زرّ تبديل ثيم صفحة الريلز (داكن/فاتح). يضبط [data-reels-theme] على <html> ويحفظ الاختيار
// في localStorage. القيمة الابتدائيّة يضبطها سكربت في الـ layout قبل الرسم (بلا وميض).
export function ReelsThemeToggle({ className }: { className?: string }) {
  const [theme, setTheme] = useState<'dark' | 'light'>('dark');
  const [ready, setReady] = useState(false);

  useEffect(() => {
    const cur = (document.documentElement.getAttribute('data-reels-theme') as 'dark' | 'light') || 'dark';
    setTheme(cur);
    setReady(true);
  }, []);

  function toggle() {
    const next = theme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-reels-theme', next);
    try {
      localStorage.setItem('reels-theme', next);
    } catch {
      /* تخزين غير متاح — يبقى للجلسة فقط */
    }
    setTheme(next);
  }

  const toLight = theme === 'dark';
  const label = toLight ? 'الوضع الفاتح' : 'الوضع الداكن';

  return (
    <button
      type="button"
      onClick={toggle}
      aria-label={label}
      className={
        'flex items-center gap-3 px-4 py-3 text-sm font-bold text-[var(--rl-fg)] transition-colors hover:bg-[var(--rl-hover)] ' +
        (className ?? '')
      }
      style={{ borderRadius: '10px' }}
    >
      <span className="grid size-5 shrink-0 place-items-center" aria-hidden>
        {/* قبل التهيئة نعرض أيقونة محايدة لتفادي عدم تطابق الترطيب */}
        {!ready || toLight ? <SunIcon /> : <MoonIcon />}
      </span>
      <span>{label}</span>
    </button>
  );
}

function SunIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.7} className="size-5" aria-hidden>
      <circle cx="12" cy="12" r="4" />
      <path strokeLinecap="round" d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41" />
    </svg>
  );
}

function MoonIcon() {
  return (
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth={1.7} className="size-5" aria-hidden>
      <path strokeLinecap="round" strokeLinejoin="round" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z" />
    </svg>
  );
}
