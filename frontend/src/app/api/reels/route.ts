import { NextResponse } from 'next/server';

import { getReelsFeed } from '@/lib/reels';

// BFF: صفحة ريلز تالية (cursor) للتمرير اللانهائيّ في الـfeed العميل.
export async function GET(request: Request) {
  const cursor = new URL(request.url).searchParams.get('cursor');
  const page = await getReelsFeed(cursor);
  return NextResponse.json(page);
}
