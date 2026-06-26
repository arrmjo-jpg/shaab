import { useLocation } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { BRAND } from '@/lib/constants';
import { coverByPath, fallbackCover } from './coverConfig';

export function AuthCover() {
  const { pathname } = useLocation();
  const { t } = useTranslation();
  const cover = coverByPath[pathname] ?? fallbackCover;
  const Illustration = cover.Illustration;

  return (
    <div className="relative hidden overflow-hidden bg-primary lg:flex lg:flex-col lg:justify-between lg:p-12 lg:text-primary-foreground">
      <div
        className="pointer-events-none absolute inset-0 opacity-60"
        style={{
          backgroundImage:
            'radial-gradient(120% 80% at 80% 0%, rgba(255,255,255,0.18), transparent 60%)',
        }}
      />
      <div className="relative z-10 flex items-center gap-2 text-lg font-bold">
        <span className="flex h-9 w-9 items-center justify-center rounded-2xl bg-white/15">
          {BRAND.name.charAt(0)}
        </span>
        {BRAND.name}
      </div>

      <div className="relative z-10 mx-auto w-full max-w-md">
        <Illustration className="mb-10 w-full text-white" />
        <h2 className="text-3xl font-bold leading-tight">
          {t(`authCover.${cover.key}.title`)}
        </h2>
        <p className="mt-3 text-base text-primary-foreground/80">
          {t(`authCover.${cover.key}.subtitle`)}
        </p>
      </div>

      <div className="relative z-10 text-sm text-primary-foreground/60">
        © {new Date().getFullYear()} {BRAND.name}
      </div>
    </div>
  );
}
