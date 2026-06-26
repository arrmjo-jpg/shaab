import { forwardAdBeacon } from '@/lib/ads-bff';

// BFF: POST /api/ads/click → POST /api/v1/ads/track/click (منارة نقر إبداع HTML؛ الصورة تستخدم
// التحويل الموقّع /ads/click/{token} مباشرةً كرابط، لا هذه المنارة).
export const dynamic = 'force-dynamic';

export async function POST(request: Request): Promise<Response> {
  return forwardAdBeacon(request, '/api/v1/ads/track/click');
}
