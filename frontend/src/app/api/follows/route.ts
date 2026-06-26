import { forwardFollowList } from '@/lib/follow-bff';

// BFF «أتابعهم»: قائمة متابعات المستخدم (يتطلّب دخولاً ⇒ 401 للزائر). تصفية اختياريّة ?type=.
export async function GET(request: Request) {
  const type = new URL(request.url).searchParams.get('type');
  return forwardFollowList({ type });
}
