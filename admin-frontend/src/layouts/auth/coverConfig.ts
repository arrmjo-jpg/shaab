import type { ComponentType } from 'react';
import { paths } from '@/router/paths';
import { LoginArt, RecoveryArt, ResetArt } from './illustrations';

export interface CoverConfig {
  /** مفتاح i18n تحت authCover.* */
  key: 'login' | 'forgot' | 'reset';
  Illustration: ComponentType<{ className?: string }>;
}

/** خريطة المسار → محتوى الغلاف (route-driven، بلا spaghetti) */
export const coverByPath: Record<string, CoverConfig> = {
  [paths.login]: { key: 'login', Illustration: LoginArt },
  [paths.forgotPassword]: { key: 'forgot', Illustration: RecoveryArt },
  [paths.resetPassword]: { key: 'reset', Illustration: ResetArt },
};

export const fallbackCover: CoverConfig = coverByPath[paths.login];
