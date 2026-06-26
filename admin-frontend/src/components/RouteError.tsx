import { useTranslation } from 'react-i18next';
import { Link, isRouteErrorResponse, useNavigate, useRouteError } from 'react-router-dom';
import { AlertTriangle, ArrowLeft, RotateCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { paths } from '@/router/paths';

/**
 * Route-level error boundary. Replaces the React Router default crash screen
 * with a translated, recoverable shell. Logs the raw error to the console for
 * developer diagnosis without leaking it to the UI.
 */
export function RouteError() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const error = useRouteError();

  let title: string;
  let detail: string | null = null;

  if (isRouteErrorResponse(error)) {
    title = `${error.status} — ${error.statusText || t('states.errorTitle')}`;
    detail = typeof error.data === 'string' ? error.data : null;
  } else if (error instanceof Error) {
    title = t('states.errorTitle');
    detail = import.meta.env.DEV ? error.message : null;
  } else {
    title = t('states.errorTitle');
  }

  // Surface the raw error in the console for diagnosis (DEV) — keep the UI clean.
  if (import.meta.env.DEV) {
    // eslint-disable-next-line no-console
    console.error('[RouteError]', error);
  }

  return (
    <div className="grid min-h-[60vh] place-items-center p-6">
      <div className="w-full max-w-md border border-border bg-background p-6 text-center">
        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center bg-destructive/10">
          <AlertTriangle className="h-7 w-7 text-destructive" />
        </div>
        <h1 className="text-lg font-bold">{title}</h1>
        {detail ? (
          <p className="mt-1 break-words text-sm text-muted-foreground">{detail}</p>
        ) : (
          <p className="mt-1 text-sm text-muted-foreground">
            {t('states.errorRetry')}
          </p>
        )}
        <div className="mt-5 flex flex-wrap items-center justify-center gap-2">
          <Button variant="outline" onClick={() => navigate(-1)}>
            <ArrowLeft className="h-4 w-4 rtl:rotate-180" />
            {t('common.back')}
          </Button>
          <Button onClick={() => window.location.reload()}>
            <RotateCw className="h-4 w-4" />
            {t('states.errorRetry')}
          </Button>
          <Button variant="ghost" asChild>
            <Link to={paths.dashboard}>{t('nav.dashboard')}</Link>
          </Button>
        </div>
      </div>
    </div>
  );
}
