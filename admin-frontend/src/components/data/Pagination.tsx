import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import type { PaginationMeta } from '@/types/users.types';

interface PaginationProps {
  meta: PaginationMeta;
  onPage: (page: number) => void;
}

/** ترقيم بسيط RTL-aware (الأسهم منطقية: السابق/التالي). */
export function Pagination({ meta, onPage }: PaginationProps) {
  const { t } = useTranslation('users');
  if (meta.total_pages <= 1) return null;

  const prev = meta.current_page - 1;
  const next = meta.current_page + 1;

  return (
    <div className="flex flex-wrap items-center justify-between gap-3 px-1 py-3">
      <p className="text-xs text-muted-foreground">
        {t('pagination.summary', {
          current: meta.current_page,
          total: meta.total_pages,
          count: meta.total,
        })}
      </p>
      <div className="flex items-center gap-2">
        <Button
          variant="outline"
          size="sm"
          disabled={prev < 1}
          onClick={() => onPage(prev)}
        >
          <ChevronRight className="h-4 w-4 rtl:hidden" />
          <ChevronLeft className="h-4 w-4 ltr:hidden" />
          {t('pagination.prev')}
        </Button>
        <Button
          variant="outline"
          size="sm"
          disabled={next > meta.total_pages}
          onClick={() => onPage(next)}
        >
          {t('pagination.next')}
          <ChevronLeft className="h-4 w-4 rtl:hidden" />
          <ChevronRight className="h-4 w-4 ltr:hidden" />
        </Button>
      </div>
    </div>
  );
}
