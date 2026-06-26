import { getCurrentUser } from '@/lib/auth';
import { getComments } from '@/lib/comments';

import { CommentForm } from './comment-form';
import { CommentList } from './comment-list';

// قسم التعليقات (Server) — يُعرَض **فقط** عند `enabled` = SSoT `commentsEnabled` (CommentGuard: عالميّ ∧ مقال).
// يجلب القائمة المعتمَدة + يحدّد حالة الدخول (getCurrentUser) لتبديل النموذج (زائر/مسجّل). لا نظام تعليقات جديد.
export async function CommentSection({ slug, enabled }: { slug: string; enabled: boolean }) {
  if (!enabled) return null;

  const [comments, user] = await Promise.all([getComments(slug), getCurrentUser()]);

  return (
    <section aria-labelledby="comments-heading" className="mt-8 border-t border-border pt-6">
      <h2 id="comments-heading" className="mb-4 text-lg font-extrabold text-fg">
        التعليقات{comments.length > 0 && <span className="ms-1 font-bold text-muted">({comments.length})</span>}
      </h2>
      <CommentList comments={comments} />
      <div className="mt-6">
        <CommentForm slug={slug} isLoggedIn={user !== null} />
      </div>
    </section>
  );
}
