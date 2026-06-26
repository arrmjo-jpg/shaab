import type { CommentItem } from '@/lib/comments';
import { formatRelativeTime } from '@/lib/format';

// قائمة التعليقات — تعليقات أعلى + ردودها (مستوى واحد، عقد الباك إند). عرض فقط، لا منطق.
export function CommentList({ comments }: { comments: CommentItem[] }) {
  if (comments.length === 0) {
    return <p className="text-sm text-muted">لا توجد تعليقات بعد — كن أوّل من يعلّق.</p>;
  }
  return (
    <ul className="space-y-4">
      {comments.map((c) => (
        <CommentNode key={c.id} c={c} />
      ))}
    </ul>
  );
}

function CommentNode({ c, isReply = false }: { c: CommentItem; isReply?: boolean }) {
  return (
    <li className={isReply ? 'mt-3 border-s-2 border-border ps-3' : 'border-b border-border pb-4 last:border-0 last:pb-0'}>
      <div className="flex items-center gap-2">
        <span className="avatar flex size-8 shrink-0 items-center justify-center bg-surface-2 text-xs font-bold text-fg">
          {c.authorName.charAt(0) || '؟'}
        </span>
        <div className="min-w-0">
          <p className="truncate text-sm font-bold text-fg">{c.authorName}</p>
          {c.createdAt && (
            <time dateTime={c.createdAt} className="text-caption text-muted">
              {formatRelativeTime(c.createdAt)}
            </time>
          )}
        </div>
      </div>
      <p className="mt-2 whitespace-pre-line text-sm leading-relaxed text-fg">{c.body}</p>
      {c.replies.length > 0 && (
        <ul className="mt-2">
          {c.replies.map((r) => (
            <CommentNode key={r.id} c={r} isReply />
          ))}
        </ul>
      )}
    </li>
  );
}
