import type { ThirdPartyUpdatePayload } from '@/types/thirdParty.types';

/** يحذف مفاتيح الأسرار الفارغة (فارغ = لا تغيير، الـ backend يُبقي القيمة). */
export function stripEmptySecrets(
  payload: ThirdPartyUpdatePayload,
  secretKeys: string[],
): ThirdPartyUpdatePayload {
  const out = { ...payload };
  for (const k of secretKeys) {
    if (out[k] === '' || out[k] === null || out[k] === undefined) delete out[k];
  }
  return out;
}
