import { forwardEngagement } from '@/lib/engagement-bff';

// BFF عامّ: تفاعل (إعجاب/عدم إعجاب) على أيّ نوع محتوى. POST يضبط، DELETE يزيل (toggle).
// الأنواع التي تتطلّب دخولاً تردّ 401 للزائر (السياسة في engagement-bff).
export async function POST(request: Request, { params }: { params: Promise<{ type: string; id: string }> }) {
  const { type, id } = await params;
  const data = await request.json().catch(() => ({}));
  const reaction = data?.reaction === 'dislike' ? 'dislike' : 'like';
  return forwardEngagement({
    type,
    id,
    action: 'react',
    method: 'POST',
    request,
    body: JSON.stringify({ reaction }),
  });
}

export async function DELETE(request: Request, { params }: { params: Promise<{ type: string; id: string }> }) {
  const { type, id } = await params;
  return forwardEngagement({ type, id, action: 'react', method: 'DELETE', request });
}
