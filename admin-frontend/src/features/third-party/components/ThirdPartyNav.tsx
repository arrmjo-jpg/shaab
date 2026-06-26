import { NavLink } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { KeyRound, ShieldCheck, Flame, MapPin, Bot, MessageCircle, Smartphone, Boxes } from 'lucide-react';
import { cn } from '@/lib/utils';
import { paths } from '@/router/paths';

const items = [
  { key: 'socialLogin', to: paths.tpSocialLogin, icon: KeyRound },
  { key: 'recaptcha', to: paths.tpRecaptcha, icon: ShieldCheck },
  { key: 'firebase', to: paths.tpFirebase, icon: Flame },
  { key: 'googleMaps', to: paths.tpGoogleMaps, icon: MapPin },
  { key: 'ai', to: paths.tpAi, icon: Bot },
  { key: 'whatsapp', to: paths.tpWhatsapp, icon: MessageCircle },
  { key: 'appLinks', to: paths.tpAppLinks, icon: Smartphone },
  { key: 'integrations', to: paths.tpIntegrations, icon: Boxes },
];

export function ThirdPartyNav() {
  const { t } = useTranslation('thirdParty');
  return (
    <nav className="flex gap-1 overflow-x-auto rounded-2xl border border-border bg-background p-2 lg:flex-col lg:overflow-visible">
      {items.map((it) => {
        const Icon = it.icon;
        return (
          <NavLink
            key={it.key}
            to={it.to}
            className={({ isActive }) =>
              cn(
                'flex items-center gap-3 whitespace-nowrap rounded-xl px-3.5 py-2.5 text-sm font-medium transition-all',
                isActive
                  ? 'bg-primary/10 text-primary'
                  : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
              )
            }
          >
            <Icon className="h-4.5 w-4.5" />
            {t(`nav.${it.key}`)}
          </NavLink>
        );
      })}
    </nav>
  );
}
