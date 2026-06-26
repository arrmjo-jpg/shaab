'use server';

import { cookies } from 'next/headers';
import { redirect } from 'next/navigation';
import { revalidatePath } from 'next/cache';

import { AUTH_COOKIE, apiFetch } from '@/lib/auth';

export async function logoutAction(): Promise<void> {
  await apiFetch('/api/v1/auth/logout', { method: 'POST' });
  (await cookies()).delete(AUTH_COOKIE);
  redirect('/login');
}

export async function markNotificationReadAction(id: string): Promise<void> {
  await apiFetch(`/api/v1/notifications/${encodeURIComponent(id)}/read`, { method: 'PATCH' });
  revalidatePath('/account/notifications');
  revalidatePath('/account');
}

export async function markAllNotificationsReadAction(): Promise<void> {
  await apiFetch('/api/v1/notifications/read-all', { method: 'PATCH' });
  revalidatePath('/account/notifications');
  revalidatePath('/account');
}

export type ActionResult = { ok: boolean; message: string };

export async function updateProfileAction(input: {
  name: string;
  bio: string;
  social_links: Record<string, string>;
}): Promise<ActionResult> {
  const res = await apiFetch('/api/v1/auth/profile', {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ name: input.name, bio: input.bio, social_links: input.social_links }),
  });
  if (!res) return { ok: false, message: 'تعذّر الاتصال بالخادم.' };
  const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));
  if (!res.ok || data?.success === false) {
    return { ok: false, message: data?.message || 'تعذّر حفظ التعديلات. تحقّق من المُدخلات.' };
  }
  revalidatePath('/account/profile');
  revalidatePath('/account');
  return { ok: true, message: 'تم حفظ التعديلات بنجاح.' };
}

export async function requestWriterUpgradeAction(note: string): Promise<ActionResult> {
  const res = await apiFetch('/api/v1/writer-requests', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ note }),
  });
  if (!res) return { ok: false, message: 'تعذّر الاتصال بالخادم.' };
  const data: { success?: boolean; message?: string } = await res.json().catch(() => ({}));
  if (!res.ok || data?.success === false) {
    return { ok: false, message: data?.message || 'تعذّر إرسال الطلب.' };
  }
  revalidatePath('/account/profile');
  revalidatePath('/account');
  return { ok: true, message: 'تم إرسال طلب الترقية إلى كاتب بنجاح، سيُراجَع قريباً.' };
}

// إنشاء محتوى ثمّ إرساله مباشرةً للمراجعة: الخادم يُنشئ مسودّةً، ثمّ انتقال الكاتب
// draft → submitted (لا حفظ كمسودّة منفصل). مشترك بين المقال والفيديو (والريل لاحقاً).
async function createAndSubmit(
  resource: 'articles' | 'videos' | 'reels',
  payload: Record<string, unknown>,
  label: string,
): Promise<ActionResult> {
  const createRes = await apiFetch(`/api/v1/${resource}`, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(payload),
  });
  if (!createRes) return { ok: false, message: 'تعذّر الاتصال بالخادم.' };
  const created: { success?: boolean; message?: string; errors?: Record<string, string[]>; data?: { id?: number } } =
    await createRes.json().catch(() => ({}));
  if (!createRes.ok || created?.success === false || !created?.data?.id) {
    const firstError = created?.errors ? Object.values(created.errors)[0]?.[0] : undefined;
    return { ok: false, message: firstError || created?.message || 'تعذّر إنشاء المحتوى. تحقّق من المُدخلات.' };
  }

  const subRes = await apiFetch(`/api/v1/${resource}/${created.data.id}/status`, {
    method: 'PATCH',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ status: 'submitted' }),
  });
  revalidatePath('/account/content');
  revalidatePath('/account');
  if (!subRes) {
    return { ok: false, message: 'تم حفظ المحتوى، لكن تعذّر إرساله للمراجعة. أعد الإرسال من «محتواي».' };
  }
  const sub: { success?: boolean; message?: string } = await subRes.json().catch(() => ({}));
  if (!subRes.ok || sub?.success === false) {
    return { ok: false, message: sub?.message || 'تم حفظ المحتوى، لكن تعذّر إرساله للمراجعة. أعد الإرسال من «محتواي».' };
  }

  return { ok: true, message: `تم إرسال ${label} للمراجعة بنجاح.` };
}

export interface CreateArticleInput {
  title: string;
  type: 'news' | 'opinion';
  primaryCategoryId: number;
  // مستند TipTap (content_json) كما يُخرجه المحرّر — يطابق قائمة سماح TipTapSanitizer.
  contentJson: unknown;
  subtitle?: string;
  tags?: string[];
  // صورة رئيسية يملكها الكاتب (طبقة الملكيّة)؛ الخادم يتحقّق عبر OwnedMediaAsset.
  coverAssetId?: number | null;
}

export async function createArticleAction(input: CreateArticleInput): Promise<ActionResult> {
  const payload: Record<string, unknown> = {
    title: input.title,
    locale: 'ar',
    type: input.type,
    primary_category_id: input.primaryCategoryId,
    content_json: input.contentJson,
  };
  if (input.subtitle) payload.subtitle = input.subtitle;
  if (input.tags?.length) payload.tags = input.tags;
  // صورة المقال الرئيسية = أصل في مجموعة cover (نفس MediaAttachmentSyncer الإداريّ).
  if (input.coverAssetId) payload.media = [{ asset_id: input.coverAssetId, collection: 'cover' }];

  const label = input.type === 'news' ? 'الخبر' : 'المقال';
  return createAndSubmit('articles', payload, label);
}

export interface CreateVideoInput {
  title: string;
  description?: string;
  // مصدر الفيديو مثل الإدارة: رفع (أصل مملوك) أو رابط خارجيّ — أحدهما.
  // (التصنيف والوسوم يكملهما المحرّر في الإدارة — ليسا في نموذج الكاتب.)
  mediaAssetId?: number | null;
  sourceUrl?: string | null;
}

export async function createVideoAction(input: CreateVideoInput): Promise<ActionResult> {
  const payload: Record<string, unknown> = { title: input.title, locale: 'ar' };
  if (input.description) payload.description = input.description;
  if (input.mediaAssetId) payload.media_asset_id = input.mediaAssetId;
  else if (input.sourceUrl) payload.source_url = input.sourceUrl;

  return createAndSubmit('videos', payload, 'الفيديو');
}

export interface CreateReelInput {
  title: string;
  description?: string;
  // فيديو الريل المرفوع (طبقة الملكيّة، ملف معالجة reel)؛ الخادم يتحقّق عبر OwnedMediaAsset.
  mediaAssetId?: number | null;
}

export async function createReelAction(input: CreateReelInput): Promise<ActionResult> {
  const payload: Record<string, unknown> = { title: input.title, locale: 'ar' };
  if (input.description) payload.description = input.description;
  if (input.mediaAssetId) payload.media_asset_id = input.mediaAssetId;

  return createAndSubmit('reels', payload, 'الريل');
}
