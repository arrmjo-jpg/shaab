const apiBaseUrl =
  (import.meta.env.VITE_API_BASE_URL as string | undefined) ?? 'http://localhost:8000/api/v1';

function originOf(url: string): string {
  try {
    return new URL(url).origin;
  } catch {
    return '';
  }
}

export const env = {
  apiBaseUrl,
  /** Public site base URL for canonical/sharing links (defaults to the API origin). */
  publicSiteUrl:
    (import.meta.env.VITE_PUBLIC_SITE_URL as string | undefined) ?? originOf(apiBaseUrl),
} as const;
