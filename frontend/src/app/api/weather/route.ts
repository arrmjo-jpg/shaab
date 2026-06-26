import { NextResponse } from 'next/server';

import { getGovernorate } from '@/lib/governorates';
import { getGovernorateWeatherFull } from '@/lib/weather';

// BFF حالة الطقس — يبقي مفتاح OpenWeather خادميّاً (لا يصل المتصفّح). العميل (weather-card) ينادي
// ‎/api/weather?gov=<id>‎ بعد تحديد الموقع/التنقّل بين المحافظات. مفتاح غير صالح ⇒ 400؛ فشل المصدر ⇒ 503.
export async function GET(request: Request): Promise<NextResponse> {
  const gov = new URL(request.url).searchParams.get('gov') ?? 'amman';
  if (!getGovernorate(gov)) {
    return NextResponse.json({ error: 'unknown_governorate' }, { status: 400 });
  }
  const data = await getGovernorateWeatherFull(gov);
  if (!data) {
    return NextResponse.json({ error: 'unavailable' }, { status: 503 });
  }
  return NextResponse.json(data, {
    headers: { 'Cache-Control': 'public, max-age=900, stale-while-revalidate=1800' },
  });
}
