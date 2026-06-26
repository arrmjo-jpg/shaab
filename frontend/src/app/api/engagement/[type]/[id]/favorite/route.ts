import { forwardEngagement } from '@/lib/engagement-bff';

// BFF عامّ: تبديل الحفظ في المفضّلة على أيّ نوع محتوى (toggle). يتطلّب تسجيل الدخول لكلّ الأنواع
// (السياسة في engagement-bff) ⇒ الزائر يحصل على 401 فيوجّهه العميل لـ/login.
export async function POST(request: Request, { params }: { params: Promise<{ type: string; id: string }> }) {
  const { type, id } = await params;
  return forwardEngagement({ type, id, action: 'favorite', method: 'POST', request });
}
