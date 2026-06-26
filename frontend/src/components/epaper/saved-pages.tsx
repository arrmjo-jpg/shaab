'use client';

import Link from 'next/link';
import { useEffect, useState } from 'react';

import type { EpaperIssue } from '@/lib/epaper';

interface SavedGroup {
  issue: EpaperIssue;
  pages: number[];
}

// محفوظاتي — يقرأ صفحات القارئ المحفوظة (bookmarks) من تخزين القارئ القائم نفسه
// (epaper:state:{id} ⇒ { bookmarks }) ويعرضها مجمّعة بالعدد، مع انتقال مباشر للصفحة.
// صفر مصدر جديد، لا تلفيق. TODO(backend reuse): للمستخدم المُسجَّل تُحفظ الإشارات خادمياً
// لكل عدد؛ لا نقطة تجميع عابرة للأعداد بعد ⇒ هنا نعرض المحفوظ محليّاً (الزائر + الكاش المحلّي).
export function SavedPages({ issues }: { issues: EpaperIssue[] }) {
  const [groups, setGroups] = useState<SavedGroup[]>([]);
  const [ready, setReady] = useState(false);

  useEffect(() => {
    try {
      const byId = new Map(issues.map((i) => [i.id, i]));
      const out: SavedGroup[] = [];
      for (let k = 0; k < localStorage.length; k++) {
        const key = localStorage.key(k);
        if (!key || !key.startsWith('epaper:state:')) continue;
        const id = Number(key.slice('epaper:state:'.length));
        const issue = byId.get(id);
        if (!issue) continue;
        const raw = localStorage.getItem(key);
        if (!raw) continue;
        const data = JSON.parse(raw) as { bookmarks?: unknown };
        const pages = (Array.isArray(data.bookmarks) ? data.bookmarks : [])
          .map(Number)
          .filter((p) => Number.isFinite(p) && p > 0)
          .sort((a, b) => a - b);
        if (pages.length) out.push({ issue, pages });
      }
      setGroups(out);
    } catch {
      /* تخزين غير متاح */
    }
    setReady(true);
  }, [issues]);

  if (!ready) return null;

  return (
    <section className="mt-12" dir="rtl" aria-labelledby="epaper-saved-heading">
      <div className="flex items-center gap-3 border-b border-border pb-3">
        <span className="h-6 w-1.5 shrink-0 bg-primary" aria-hidden />
        <h2 id="epaper-saved-heading" className="text-xl font-extrabold text-fg">
          محفوظاتي
        </h2>
      </div>

      {groups.length === 0 ? (
        <p className="py-8 text-center text-sm text-muted">لا توجد صفحات محفوظة بعد.</p>
      ) : (
        <div className="mt-4 grid gap-4">
          {groups.map(({ issue, pages }) => (
            <div key={issue.id} className="border border-border p-4">
              <p className="text-sm font-bold text-fg">
                عدد #{issue.issueNumber} — {issue.title}
              </p>
              <div className="mt-2 flex flex-wrap gap-2">
                {pages.map((p) => (
                  <Link
                    key={p}
                    href={`${issue.readHref}/p/${p}`}
                    className="inline-flex items-center border border-border px-3 py-1 text-xs font-bold text-fg transition hover:bg-surface-2"
                  >
                    صفحة {p}
                  </Link>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </section>
  );
}
