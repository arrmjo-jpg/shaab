import { forwardEngagement } from '@/lib/engagement-bff';

// BFF منارة المشاهدة — POST يمرّر التوكن (meta.view_token) إلى الباك إند المركزيّ
//   POST /api/v1/engagement/{type}/{id}/view   body: { token }   header: X-Client-Id
// عامّ (لا يتطلّب دخولاً): الفاعل هجين (Bearer إن وُجد + بصمة الزائر). منع التكرار + حدّ المعدّل
// في الباك إند. توكن مفقود/غير صالح ⇒ الباك إند يردّ 422 (لا احتساب).
export async function POST(request: Request, { params }: { params: Promise<{ type: string; id: string }> }) {
  const { type, id } = await params;
  const data = await request.json().catch(() => ({}));
  const token = typeof data?.token === 'string' ? data.token : '';
  return forwardEngagement({
    type,
    id,
    action: 'view',
    method: 'POST',
    request,
    body: JSON.stringify({ token }),
  });
}
