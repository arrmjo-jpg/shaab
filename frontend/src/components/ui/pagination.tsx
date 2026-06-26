import { ChevronLeft, ChevronRight } from 'lucide-react';
import Link from 'next/link';

// ترقيم صفحات احترافيّ قابل لإعادة الاستخدام (RTL): سابق/تالٍ + أرقام مع حذف (…) للصفحات الكثيرة.
// **خادميّ بحت** (روابط فقط، صفر JS) — كلّ صفحة رابط مستقلّ (SEO + تنقّل Next). يُخفى عند صفحة واحدة.
// hrefFor(page) يبني رابط الصفحة (يحتفظ بسياق المسار/الاستعلام للمستهلك). صلة rel=prev/next.
export function Pagination({
  currentPage,
  totalPages,
  hrefFor,
}: {
  currentPage: number;
  totalPages: number;
  hrefFor: (page: number) => string;
}) {
  if (totalPages <= 1) return null;
  const page = Math.min(Math.max(1, currentPage), totalPages);
  const items = paginationRange(page, totalPages);

  const base =
    'flex h-10 min-w-10 items-center justify-center rounded-md border px-3 text-sm font-bold tabular-nums transition';
  const link = `${base} border-border text-fg hover:border-primary hover:text-primary`;
  const off = `${base} border-border text-muted/40`;

  return (
    <nav className="mt-10 flex flex-wrap items-center justify-center gap-2" aria-label="ترقيم الصفحات">
      {page > 1 ? (
        <Link href={hrefFor(page - 1)} rel="prev" aria-label="الصفحة السابقة" className={link}>
          <ChevronRight className="size-4" aria-hidden />
        </Link>
      ) : (
        <span className={off} aria-hidden>
          <ChevronRight className="size-4" />
        </span>
      )}

      {items.map((it, i) =>
        it === '…' ? (
          <span key={`gap-${i}`} className="px-1 text-muted" aria-hidden>
            …
          </span>
        ) : it === page ? (
          <span key={it} aria-current="page" className={`${base} border-primary bg-primary text-primary-foreground`}>
            {it}
          </span>
        ) : (
          <Link key={it} href={hrefFor(it)} className={link}>
            {it}
          </Link>
        ),
      )}

      {page < totalPages ? (
        <Link href={hrefFor(page + 1)} rel="next" aria-label="الصفحة التالية" className={link}>
          <ChevronLeft className="size-4" aria-hidden />
        </Link>
      ) : (
        <span className={off} aria-hidden>
          <ChevronLeft className="size-4" />
        </span>
      )}
    </nav>
  );
}

// نطاق الصفحات المعروضة مع حذف: [1, …, 4, 5, 6, …, 20]. يُظهر دائماً الأولى/الأخيرة + الحاليّة ±1.
function paginationRange(current: number, total: number): (number | '…')[] {
  const sibling = 1;
  const shown = sibling * 2 + 5; // الأولى + الأخيرة + الحاليّة + شقيقتان + حذفان
  if (total <= shown) {
    return Array.from({ length: total }, (_, i) => i + 1);
  }

  const left = Math.max(current - sibling, 1);
  const right = Math.min(current + sibling, total);
  const leftDots = left > 2;
  const rightDots = right < total - 1;

  const range: (number | '…')[] = [1];
  if (leftDots) {
    range.push('…');
  }
  for (let i = leftDots ? left : 2; i <= (rightDots ? right : total - 1); i++) {
    range.push(i);
  }
  if (rightDots) {
    range.push('…');
  }
  range.push(total);

  return range;
}
