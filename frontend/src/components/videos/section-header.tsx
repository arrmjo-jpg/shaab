import { ChevronLeft } from 'lucide-react';
import Link from 'next/link';
import type { ReactNode } from 'react';

// ترويسة قسم مُعاد الاستخدام — `eyebrow` اختياريّ (تمييز نوع القسم، مثل «قائمة تشغيل») + شريط أحمر (أو `leading`
// بديل) + عنوان + سطر ميتا (عدد/مدّة) + «عرض الكل». مُقدِّميّة بحتة (كلّ النصوص/الروابط props — لا hardcoding).
// مربّعة، tokens، أدوات لوجيّة، RTL/LTR.
export function SectionHeader({
  title,
  id,
  eyebrow,
  subtitle,
  meta,
  leading,
  icon,
  viewAllHref,
  viewAllLabel = 'عرض الكل',
}: {
  title: string;
  id?: string;
  eyebrow?: string;
  subtitle?: string;
  meta?: ReactNode;
  leading?: ReactNode;
  icon?: ReactNode;
  viewAllHref?: string;
  viewAllLabel?: string;
}) {
  return (
    <div className="mb-5 flex items-end justify-between gap-4 border-b border-border pb-4">
      <div className="flex min-w-0 items-center gap-3">
        {leading ?? <span className="h-7 w-1.5 shrink-0 bg-primary" aria-hidden />}
        <div className="min-w-0">
          {eyebrow && (
            <span className="block text-[11px] font-bold uppercase tracking-wider text-muted">{eyebrow}</span>
          )}
          <h2 id={id} className="flex items-center gap-2 text-xl font-extrabold tracking-tight text-fg sm:text-2xl">
            {icon}
            {viewAllHref ? (
              <Link href={viewAllHref} className="transition-colors hover:text-primary">
                {title}
              </Link>
            ) : (
              title
            )}
          </h2>
          {subtitle && <p className="mt-1 line-clamp-1 text-sm text-muted">{subtitle}</p>}
          {meta && <div className="mt-1 flex flex-wrap items-center gap-2 text-caption text-muted">{meta}</div>}
        </div>
      </div>

      {viewAllHref && (
        <Link
          href={viewAllHref}
          className="flex shrink-0 items-center gap-1 text-sm font-bold text-muted transition-colors hover:text-primary"
        >
          {viewAllLabel}
          <ChevronLeft className="size-4" aria-hidden />
        </Link>
      )}
    </div>
  );
}
