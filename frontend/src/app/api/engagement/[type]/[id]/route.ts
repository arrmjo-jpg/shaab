import { forwardEngagement } from '@/lib/engagement-bff';

// BFF عامّ: حالة التفاعل الحاليّة للمستخدم/الزائر (reaction/favorited/metrics) — لترطيب الواجهة
// client-side بأمان كاش (لا تُخبز حالة المستخدم في صفحة مُكاشة). قراءة عامّة (لا 401).
export async function GET(request: Request, { params }: { params: Promise<{ type: string; id: string }> }) {
  const { type, id } = await params;
  return forwardEngagement({ type, id, action: 'state', method: 'GET', request });
}
