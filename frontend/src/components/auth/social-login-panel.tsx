import type { ComponentType } from 'react';

import { FacebookIcon, GoogleIcon, type SocialIconProps } from '@/components/icons';
import type { SocialProvider } from '@/lib/auth-config';

// Provider id → brand icon + label. Backend currently models google + facebook (see ThirdPartySettings).
const PROVIDER_UI: Record<string, { Icon: ComponentType<SocialIconProps>; label: string }> = {
  google: { Icon: GoogleIcon, label: 'الدخول عبر Google' },
  facebook: { Icon: FacebookIcon, label: 'الدخول عبر Facebook' },
};

// Social-login panel — renders one button per enabled provider (data from the backend). The parent
// only mounts this when providers exist; with none, the page is single-column.
export function SocialLoginPanel({ providers }: { providers: SocialProvider[] }) {
  return (
    <div className="flex h-full flex-col justify-center gap-5">
      <div>
        <h2 className="font-heading text-h3 font-bold text-fg">تسجيل الدخول السريع</h2>
        <p className="mt-2 text-sm leading-relaxed text-muted">
          يمكنك تسجيل الدخول مباشرة باستخدام حسابات التواصل الاجتماعي.
        </p>
      </div>

      <div className="flex flex-col gap-3">
        {providers.map((provider) => {
          const ui = PROVIDER_UI[provider.id];
          if (!ui) return null;
          const { Icon, label } = ui;
          return (
            <a
              key={provider.id}
              href={provider.redirect_url}
              className="inline-flex items-center justify-center gap-3 border border-border bg-surface px-4 py-3 text-sm font-medium text-fg transition-colors hover:bg-surface-2 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            >
              <Icon className="size-5" aria-hidden />
              {label}
            </a>
          );
        })}
      </div>
    </div>
  );
}
