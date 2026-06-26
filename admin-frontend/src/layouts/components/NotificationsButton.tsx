import { Bell } from 'lucide-react';
import { useTranslation } from 'react-i18next';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuLabel,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { EmptyState } from '@/components/feedback';

/**
 * عنصر إشعارات هيكلي فقط — لا يوجد endpoint إشعارات ضمن النطاق.
 * يُوصَل بالـ API لاحقاً عند توفّره (لا نداء وهمي).
 */
export function NotificationsButton() {
  const { t } = useTranslation();
  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button variant="ghost" size="icon" aria-label={t('shell.notifications')}>
          <Bell className="h-5 w-5" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end" className="w-80">
        <DropdownMenuLabel>{t('shell.notifications')}</DropdownMenuLabel>
        <EmptyState title={t('shell.noNotifications')} />
      </DropdownMenuContent>
    </DropdownMenu>
  );
}
