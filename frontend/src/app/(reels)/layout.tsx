import type { ReactNode } from 'react';

import { ReelsSidebar } from '@/components/reels/reels-sidebar';

// سكربت ما-قبل-الرسم: يضبط [data-reels-theme] على <html> من localStorage أو إعداد الجهاز
// قبل أوّل رسمة (لا وميض ثيم). يعمل ضمن مجموعة الريلز فقط.
const THEME_INIT = `(function(){try{var t=localStorage.getItem('reels-theme');if(t!=='light'&&t!=='dark'){t=matchMedia('(prefers-color-scheme: light)').matches?'light':'dark';}document.documentElement.setAttribute('data-reels-theme',t);}catch(e){}})();`;

// مجموعة مسار الريلز — قشرة مستقلّة بملء الشاشة تدعم الداكن/الفاتح (data-reels-theme)، بلا
// هيدر/شريط أقسام/فوتر الموقع. سطح المكتب: شريط جانبيّ (تنقّل/خدمات/حساب/سوشيل) + الـfeed.
export default function ReelsLayout({ children }: { children: ReactNode }) {
  return (
    <div className="reels-scope bg-[var(--rl-bg)] md:flex md:h-[100dvh]">
      <script dangerouslySetInnerHTML={{ __html: THEME_INIT }} />
      <style>{`.reels-feed::-webkit-scrollbar{display:none}.reels-feed{scrollbar-width:none}`}</style>
      <ReelsSidebar />
      <div className="min-w-0 flex-1">{children}</div>
    </div>
  );
}
