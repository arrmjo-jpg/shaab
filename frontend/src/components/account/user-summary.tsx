import type { AccountUser } from '@/lib/auth';

// Human role label from the real user record (no hardcoded role).
export function roleLabel(user: Pick<AccountUser, 'is_writer' | 'writer_request'>): string {
  if (user.is_writer) return 'كاتب';
  if (user.writer_request?.status === 'pending') return 'بانتظار الموافقة';
  return 'قارئ';
}

// Avatar + name + role. Shared (server + client safe) — receives only serializable props.
export function UserSummary({
  name,
  avatar,
  role,
}: {
  name: string;
  avatar?: string | null;
  role: string;
}) {
  const initial = name?.trim().charAt(0) || '؟';

  return (
    <div className="flex items-center gap-3">
      <div className="avatar flex size-11 shrink-0 items-center justify-center overflow-hidden rounded-full bg-surface-2 text-fg">
        {avatar ? (
          // eslint-disable-next-line @next/next/no-img-element -- raw <img> until the unified Image-Platform slice
          <img src={avatar} alt={name} className="size-full object-cover" />
        ) : (
          <span className="font-heading text-lg font-bold">{initial}</span>
        )}
      </div>
      <div className="min-w-0">
        <p className="truncate text-sm font-bold text-fg">{name}</p>
        <p className="truncate text-caption text-muted">{role}</p>
      </div>
    </div>
  );
}
