import { forwardFollow } from '@/lib/follow-bff';

// BFF «تابع»: حالة المتابعة (GET، عامّة — الزائر following:false) + تبديلها (POST، يتطلّب دخولاً ⇒ 401→login).
export async function GET(request: Request, { params }: { params: Promise<{ type: string; id: string }> }) {
  const { type, id } = await params;
  return forwardFollow({ type, id, action: 'state', request });
}

export async function POST(request: Request, { params }: { params: Promise<{ type: string; id: string }> }) {
  const { type, id } = await params;
  return forwardFollow({ type, id, action: 'toggle', request });
}
