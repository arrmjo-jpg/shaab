import { Link, useLocation } from 'react-router-dom';
import { ChevronLeft } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { allNavItems } from '@/config/navigation';
import { paths } from '@/router/paths';

export function Breadcrumbs() {
  const { pathname } = useLocation();
  const { t } = useTranslation();

  const match = allNavItems.find(
    (n) => n.to === pathname || (n.to !== paths.dashboard && pathname.startsWith(n.to)),
  );

  return (
    <nav className="flex items-center gap-2 text-sm text-muted-foreground">
      <Link to={paths.dashboard} className="transition-colors hover:text-foreground">
        {t('nav.dashboard')}
      </Link>
      {match && match.to !== paths.dashboard ? (
        <>
          <ChevronLeft className="h-4 w-4 rtl:rotate-180" />
          <span className="font-medium text-foreground">{t(`nav.${match.key}`)}</span>
        </>
      ) : null}
    </nav>
  );
}
