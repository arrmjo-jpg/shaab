import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Inbox, Mail, Megaphone } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useAuth } from '@/hooks/useAuth';
import { useInboxUnread } from '../contact.hooks';
import { ContactMessagesTab } from '../components/ContactMessagesTab';
import { AdRequestsTab } from '../components/AdRequestsTab';

type TabKey = 'contact' | 'ads';

/**
 * صندوق الوارد (الإدارة) — تبويبان: رسائل الاتصال + طلبات الإعلان. كلّ تبويب يظهر فقط لمن يملك
 * صلاحية رؤيته. عدّادات «new» على التبويبات من نفس مصدر شارة النافبار (SSoT).
 */
export default function ContactUsPage() {
  const { t } = useTranslation('inbox');
  const { hasPermission } = useAuth();

  const canViewContact = hasPermission('contact-messages.view');
  const canViewAds = hasPermission('ad-requests.view');

  const unread = useInboxUnread(canViewContact || canViewAds);

  const tabs: { key: TabKey; label: string; icon: typeof Mail; count: number; shown: boolean }[] = [
    {
      key: 'contact',
      label: t('tabs.contact'),
      icon: Mail,
      count: unread.data?.contact_count ?? 0,
      shown: canViewContact,
    },
    {
      key: 'ads',
      label: t('tabs.ads'),
      icon: Megaphone,
      count: unread.data?.ad_count ?? 0,
      shown: canViewAds,
    },
  ];

  const available = tabs.filter((tab) => tab.shown);
  const [active, setActive] = useState<TabKey>(available[0]?.key ?? 'contact');

  return (
    <div className="space-y-6">
      <header className="flex items-center gap-2">
        <Inbox className="h-6 w-6 text-muted-foreground" />
        <div>
          <h1 className="text-2xl font-bold">{t('title')}</h1>
          <p className="text-sm text-muted-foreground">{t('subtitle')}</p>
        </div>
      </header>

      <div className="flex items-center gap-1 border-b border-border">
        {available.map((tab) => {
          const Icon = tab.icon;
          const isActive = active === tab.key;
          return (
            <button
              key={tab.key}
              type="button"
              onClick={() => setActive(tab.key)}
              className={cn(
                '-mb-px flex items-center gap-2 border-b-2 px-4 py-2.5 text-sm font-medium transition-colors',
                isActive
                  ? 'border-primary text-foreground'
                  : 'border-transparent text-muted-foreground hover:text-foreground',
              )}
            >
              <Icon className="h-4 w-4" />
              {tab.label}
              {tab.count > 0 ? (
                <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-destructive px-1.5 text-[10px] font-bold leading-none text-destructive-foreground">
                  {tab.count > 99 ? '99+' : tab.count}
                </span>
              ) : null}
            </button>
          );
        })}
      </div>

      {active === 'contact' && canViewContact ? <ContactMessagesTab /> : null}
      {active === 'ads' && canViewAds ? <AdRequestsTab /> : null}
    </div>
  );
}
