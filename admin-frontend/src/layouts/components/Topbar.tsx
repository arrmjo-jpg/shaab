import { ExternalLink, Menu, Search } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import { env } from '@/lib/env';
import { Breadcrumbs } from './Breadcrumbs';
import { ThemeToggle } from './ThemeToggle';
import { ChatButton } from './ChatButton';
import { ContactButton } from './ContactButton';
import { NotificationsButton } from './NotificationsButton';
import { UserMenu } from './UserMenu';

export function Topbar({ onOpenSidebar }: { onOpenSidebar: () => void }) {
  const { t } = useTranslation();
  return (
    <header className="sticky top-0 z-30 flex h-16 items-center gap-3 border-b border-border bg-background/80 px-4 backdrop-blur sm:px-6">
      <Button
        variant="ghost"
        size="icon"
        className="lg:hidden"
        onClick={onOpenSidebar}
        aria-label={t('shell.toggleSidebar')}
      >
        <Menu className="h-5 w-5" />
      </Button>

      <Breadcrumbs />

      <div className="ms-auto flex items-center gap-2">
        <button
          type="button"
          className="hidden items-center gap-2 rounded-xl border border-border bg-secondary/60 px-3 py-2 text-sm text-muted-foreground transition-colors hover:bg-secondary md:flex"
        >
          <Search className="h-4 w-4" />
          {t('shell.search')}
        </button>
        {env.publicSiteUrl ? (
          <Button
            asChild
            variant="ghost"
            size="icon"
            aria-label={t('shell.viewSite')}
            title={t('shell.viewSite')}
          >
            <a
              href={env.publicSiteUrl}
              target="_blank"
              rel="noopener noreferrer"
            >
              <ExternalLink className="h-5 w-5" />
            </a>
          </Button>
        ) : null}
        <ChatButton />
        <ContactButton />
        <ThemeToggle />
        <NotificationsButton />
        <UserMenu />
      </div>
    </header>
  );
}
