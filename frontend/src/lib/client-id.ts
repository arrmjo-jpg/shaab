// معرّف عميل ثابت للزائر (بصمة) — يُرسَل كترويسة X-Client-Id إلى الـBFF، فيمرّره للباك إند
// ليُسنِد تفاعل الزائر إلى فاعل ثابت (EngagementActor الهجين: مستخدم Bearer أو بصمة زائر).
// عميل فقط (localStorage). انظر .ai/advertising.md §6 (نفس عقد الفاعل عبر المنصّة).
const KEY = 'acm_cid';

export function getClientId(): string {
  if (typeof window === 'undefined') return '';
  try {
    let id = window.localStorage.getItem(KEY);
    if (!id) {
      id =
        typeof crypto !== 'undefined' && 'randomUUID' in crypto
          ? crypto.randomUUID()
          : `c-${Date.now().toString(36)}-${Math.random().toString(36).slice(2)}`;
      window.localStorage.setItem(KEY, id);
    }
    return id;
  } catch {
    return '';
  }
}
