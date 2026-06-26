import Link from 'next/link';
import type { ComponentType } from 'react';

import { cn } from '@/lib/utils';

// شريط تبويبات الحساب **العامّ** (URL-driven عبر `?{param}=`). مصدر واحد لكلّ تبويبات الحساب:
// «محتواي» (مقالات/فيديو/ريلز) و«أعجبني»/«المحفوظات» (الكل/المقالات/الفيديوهات/الريلز) — نفس
// الشكل (حدّ سفليّ نشط)، بلا تكرار. كلّ مستهلك يمرّر تبويباته + مساره الأساس.
export interface AccountTab {
  key: string;
  label: string;
  icon?: ComponentType<{ className?: string }>;
}

export function AccountTabs({
  tabs,
  basePath,
  active,
  param = 'tab',
}: {
  tabs: AccountTab[];
  basePath: string;
  active: string;
  param?: string;
}) {
  return (
    <div className="flex gap-1 overflow-x-auto overflow-y-hidden border-b border-border">
      {tabs.map((t) => {
        const Icon = t.icon;
        return (
          <Link
            key={t.key}
            href={`${basePath}?${param}=${t.key}`}
            aria-current={active === t.key ? 'page' : undefined}
            className={cn(
              '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
              active === t.key ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-fg',
            )}
          >
            {Icon && <Icon className="size-4" aria-hidden />}
            {t.label}
          </Link>
        );
      })}
    </div>
  );
}
