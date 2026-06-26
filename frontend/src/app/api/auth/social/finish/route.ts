import { NextResponse } from 'next/server';

import { env } from '@/lib/env';

// OAuth handoff: the backend social callback redirects here with the issued token. We store it as an
// httpOnly cookie (never exposed to JS) and bounce to the homepage. (Consuming the cookie for /me,
// protected routes and logout is the auth-session slice.)
export async function GET(request: Request): Promise<Response> {
  const url = new URL(request.url);
  const token = url.searchParams.get('token');
  const res = NextResponse.redirect(new URL('/account', url.origin));

  if (token) {
    res.cookies.set('auth_token', token, {
      httpOnly: true,
      secure: env.isProd,
      sameSite: 'lax',
      path: '/',
      maxAge: 60 * 60 * 24 * 30,
    });
  }

  return res;
}
