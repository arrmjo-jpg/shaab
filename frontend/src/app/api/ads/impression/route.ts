import { forwardAdBeacon } from '@/lib/ads-bff';

// BFF: POST /api/ads/impression → POST /api/v1/ads/track/impression (منارة الظهور، رمز واحد).
export const dynamic = 'force-dynamic';

export async function POST(request: Request): Promise<Response> {
  return forwardAdBeacon(request, '/api/v1/ads/track/impression');
}
