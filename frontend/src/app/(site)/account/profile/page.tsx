import { AvatarUpload } from '@/components/account/avatar-upload';
import { ProfileForm } from '@/components/account/profile-form';
import { roleLabel } from '@/components/account/user-summary';
import { WriterRequestCard } from '@/components/account/writer-request-card';
import { getCurrentUser } from '@/lib/auth';
import { formatDate } from '@/lib/format';

// Account/edit design uses SQUARE containers (no border-radius) per design request.
export default async function ProfilePage() {
  const user = await getCurrentUser();
  if (!user) return null;
  const social = (user.social_links ?? {}) as Record<string, string>;

  return (
    <div className="mx-auto flex max-w-2xl flex-col gap-6">
      <h1 className="font-heading text-h2 font-extrabold text-fg">ملفي الشخصي</h1>

      {/* Identity + avatar upload */}
      <div className="flex flex-col gap-4 border border-border bg-surface p-5">
        <AvatarUpload avatar={user.avatar ?? null} name={user.name} />
        <div className="border-t border-border pt-4">
          <p className="font-heading text-lg font-bold text-fg">{user.name}</p>
          <p className="text-sm text-muted">{user.email}</p>
          <div className="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-caption text-muted">
            <span className="bg-primary/10 px-2 py-0.5 font-bold text-primary">{roleLabel(user)}</span>
            <span>انضمّ {formatDate(user.created_at)}</span>
          </div>
        </div>
      </div>

      {/* Edit account (square section) */}
      <section className="border border-border bg-surface">
        <div className="border-b border-border px-5 py-3.5">
          <h2 className="font-heading text-base font-bold text-fg">تعديل الحساب</h2>
        </div>
        <div className="p-5">
          <ProfileForm name={user.name} bio={user.bio ?? ''} social={social} />
        </div>
      </section>

      {/* Writer upgrade — non-writers only */}
      {!user.is_writer && <WriterRequestCard status={user.writer_request?.status ?? null} />}
    </div>
  );
}
