import type { ReactNode } from 'react';
import { useForm, Controller } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import { useNavigate, useParams, Link } from 'react-router-dom';
import {
  User,
  Lock,
  FileText,
  Share2,
  ShieldCheck,
  Link2,
  ArrowRight,
  Save,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { PasswordField } from '@/components/form/PasswordField';
import { SelectField } from '@/components/form/SelectField';
import { SwitchField } from '@/components/form/SwitchField';
import { PageSkeleton, ErrorState } from '@/components/feedback';
import { paths } from '@/router/paths';
import { userFormSchema, type UserFormValues } from '../schemas';
import {
  useCreateUser,
  useUpdateUser,
  useAllRoles,
  useUser,
} from '../hooks';
import { AvatarUpload } from '../components/AvatarUpload';
import { useToast } from '@/hooks/useToast';
import type { UserUpsertPayload } from '@/types/users.types';
import type { NormalizedError } from '@/types/api';

const STATUSES = ['active', 'suspended', 'banned'] as const;
const SOCIALS = [
  { key: 'facebook', color: 'text-[#1877F2]' },
  { key: 'twitter_x', color: 'text-foreground' },
  { key: 'instagram', color: 'text-[#E4405F]' },
  { key: 'tiktok', color: 'text-foreground' },
  { key: 'linkedin', color: 'text-[#0A66C2]' },
  { key: 'youtube', color: 'text-[#FF0000]' },
] as const;

type SocialKey = (typeof SOCIALS)[number]['key'];

function Panel({
  icon,
  title,
  hint,
  tone,
  children,
}: {
  icon: ReactNode;
  title: string;
  hint: string;
  tone: string;
  children: ReactNode;
}) {
  return (
    <section className="rounded-2xl border border-border bg-background p-5">
      <div className="mb-5 flex items-center gap-3">
        <div className={`flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ${tone}`}>
          {icon}
        </div>
        <div>
          <h3 className="text-sm font-bold">{title}</h3>
          <p className="text-xs text-muted-foreground">{hint}</p>
        </div>
      </div>
      {children}
    </section>
  );
}

export default function UserFormPage() {
  const { t } = useTranslation('users');
  const { error: toastError } = useToast();
  const navigate = useNavigate();
  const { id } = useParams();
  const userId = id ? Number(id) : null;
  const isEdit = userId !== null;

  const userQ = useUser(userId);
  const rolesQ = useAllRoles();
  const create = useCreateUser();
  const update = useUpdateUser();

  const user = userQ.data ?? null;

  const { register, handleSubmit, watch, setValue, setError, control, formState } =
    useForm<UserFormValues>({
      resolver: zodResolver(userFormSchema),
      values: {
        name: user?.name ?? '',
        email: user?.email ?? '',
        password: '',
        password_confirmation: '',
        status: (user?.status as UserFormValues['status']) ?? 'active',
        email_verified: user?.email_verified ?? false,
        is_writer: user?.is_writer ?? false,
        avatar: user?.avatar ?? '',
        bio: user?.bio ?? '',
        socials: {
          facebook: user?.social_links?.facebook ?? '',
          twitter_x: user?.social_links?.twitter_x ?? '',
          instagram: user?.social_links?.instagram ?? '',
          tiktok: user?.social_links?.tiktok ?? '',
          linkedin: user?.social_links?.linkedin ?? '',
          youtube: user?.social_links?.youtube ?? '',
        },
        roles: user?.roles.map((r) => r.name) ?? [],
      },
    });

  if (isEdit && userQ.isLoading) return <PageSkeleton />;
  if (isEdit && userQ.isError) return <ErrorState onRetry={() => void userQ.refetch()} />;

  const selectedRoles = watch('roles');

  const toggleRole = (name: string) => {
    const next = selectedRoles.includes(name)
      ? selectedRoles.filter((r) => r !== name)
      : [...selectedRoles, name];
    setValue('roles', next, { shouldDirty: true });
  };

  // ربط أخطاء التحقّق من الـ backend (422) بحقول النموذج — بدل رسالة عامة
  // غامضة فقط. يغطّي ما قد يفلت من تحقّق العميل (بريد مكرّر، سياسة كلمة المرور…).
  const applyServerErrors = (e: NormalizedError): void => {
    const map: Partial<Record<string, keyof UserFormValues>> = {
      name: 'name',
      email: 'email',
      password: 'password',
      password_confirmation: 'password_confirmation',
      status: 'status',
      avatar: 'avatar',
      bio: 'bio',
    };
    Object.entries(e.errors ?? {}).forEach(([key, msgs]) => {
      const msg = msgs?.[0];
      if (!msg) return;
      const field = key.startsWith('roles') ? 'roles' : map[key];
      if (field) setError(field, { message: msg });
    });
  };

  const submit = handleSubmit((values) => {
    if (!isEdit && !values.password) {
      setError('password', { message: 'users:validation.password' });
      return;
    }

    const social_links: Record<string, string> = {};
    (Object.entries(values.socials) as [SocialKey, string | undefined][]).forEach(
      ([k, v]) => {
        if (v) social_links[k] = v;
      },
    );

    const payload: UserUpsertPayload = {
      name: values.name,
      email: values.email,
      avatar: values.avatar || null,
      bio: values.bio || null,
      social_links,
      email_verified: values.email_verified,
      is_writer: values.is_writer,
      roles: values.roles,
    };
    if (values.password) {
      payload.password = values.password;
      payload.password_confirmation = values.password_confirmation;
    }
    if (!isEdit) payload.status = values.status;

    const onDone = {
      onSuccess: () => navigate(paths.users),
      onError: applyServerErrors,
    };
    if (isEdit) update.mutate({ id: userId, payload }, onDone);
    else create.mutate(payload, onDone);
  }, () => {
    // فشل تحقّق client-side: لا يبقى صامتاً
    toastError(t('users.form.invalid'));
  });

  const saving = create.isPending || update.isPending;
  const roles = rolesQ.data?.data ?? [];

  return (
    <div className="space-y-6 pb-24">
      <header className="space-y-2">
        <nav className="flex items-center gap-2 text-sm text-muted-foreground">
          <Link to={paths.users} className="hover:text-foreground">
            {t('users.title')}
          </Link>
          <span>/</span>
          <span className="text-foreground">
            {isEdit ? t('users.form.editTitle') : t('users.form.createTitle')}
          </span>
        </nav>
        <h1 className="text-2xl font-bold">
          {isEdit ? t('users.form.editTitle') : t('users.form.createTitle')}
        </h1>
      </header>

      <form onSubmit={submit} className="space-y-5" noValidate>
        <div className="grid gap-5 lg:grid-cols-3">
          <Panel
            icon={<User className="h-5 w-5 text-primary" />}
            tone="bg-primary/10"
            title={t('users.form.secProfile')}
            hint={t('users.form.secProfileHint')}
          >
            <Controller
              control={control}
              name="avatar"
              render={({ field }) => (
                <AvatarUpload value={field.value ?? ''} onChange={field.onChange} />
              )}
            />
          </Panel>

          <div className="lg:col-span-2">
            <Panel
              icon={<User className="h-5 w-5 text-primary" />}
              tone="bg-primary/10"
              title={t('users.form.secBasic')}
              hint={t('users.form.secBasicHint')}
            >
              <div className="grid gap-4 sm:grid-cols-2">
                <TextField
                  label={t('users.form.name')}
                  error={formState.errors.name}
                  {...register('name')}
                />
                <TextField
                  label={t('users.form.email')}
                  type="email"
                  dir="ltr"
                  error={formState.errors.email}
                  {...register('email')}
                />
                <SelectField
                  label={t('users.form.status')}
                  options={STATUSES.map((s) => ({
                    value: s,
                    label: t(`users.status.${s}`),
                  }))}
                  disabled={isEdit}
                  {...register('status')}
                />
              </div>
              <div className="mt-4">
                <Controller
                  control={control}
                  name="email_verified"
                  render={({ field }) => (
                    <SwitchField
                      label={t('users.form.emailVerified')}
                      description={t('users.form.emailVerifiedHint')}
                      checked={field.value}
                      onChange={field.onChange}
                    />
                  )}
                />
              </div>
              <div className="mt-4">
                <Controller
                  control={control}
                  name="is_writer"
                  render={({ field }) => (
                    <SwitchField
                      label={t('users.form.isWriter')}
                      description={t('users.form.isWriterHint')}
                      checked={field.value}
                      onChange={field.onChange}
                    />
                  )}
                />
              </div>
            </Panel>
          </div>
        </div>

        <Panel
          icon={<Lock className="h-5 w-5 text-destructive" />}
          tone="bg-destructive/10"
          title={t('users.form.secSecurity')}
          hint={
            isEdit
              ? t('users.form.secSecurityHintEdit')
              : t('users.form.secSecurityHintCreate')
          }
        >
          <div className="grid gap-4 sm:grid-cols-2">
            <PasswordField
              label={isEdit ? t('users.form.passwordKeep') : t('users.form.password')}
              error={formState.errors.password}
              {...register('password')}
            />
            <PasswordField
              label={t('users.form.passwordConfirm')}
              error={formState.errors.password_confirmation}
              {...register('password_confirmation')}
            />
          </div>
        </Panel>

        <Panel
          icon={<FileText className="h-5 w-5 text-primary" />}
          tone="bg-primary/10"
          title={t('users.form.bio')}
          hint={t('users.form.secProfileHint')}
        >
          <TextareaField label={t('users.form.bio')} {...register('bio')} />
        </Panel>

        <Panel
          icon={<Share2 className="h-5 w-5 text-primary" />}
          tone="bg-primary/10"
          title={t('users.form.secSocial')}
          hint={t('users.form.secSocialHint')}
        >
          <div className="grid gap-4 sm:grid-cols-2">
            {SOCIALS.map((s) => (
              <div key={s.key} className="space-y-1.5">
                <span className="flex items-center gap-2 text-sm font-medium">
                  <Link2 className={`h-4 w-4 ${s.color}`} />
                  {t(`users.form.soc.${s.key}`)}
                </span>
                <input
                  dir="ltr"
                  placeholder="https://"
                  className="flex h-11 w-full rounded-xl border border-input bg-background px-3.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                  {...register(`socials.${s.key}` as const)}
                />
                {(formState.errors.socials as Record<string, unknown> | undefined)?.[
                  s.key
                ] ? (
                  <p className="text-xs font-medium text-destructive">
                    {t('validation.url')}
                  </p>
                ) : null}
              </div>
            ))}
          </div>
        </Panel>

        <Panel
          icon={<ShieldCheck className="h-5 w-5 text-emerald-500" />}
          tone="bg-emerald-500/10"
          title={t('users.form.secRoles')}
          hint={t('users.form.secRolesHint')}
        >
          {roles.length === 0 ? (
            <p className="text-sm text-muted-foreground">{t('users.form.noRoles')}</p>
          ) : (
            <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
              {roles.map((r) => {
                const checked = selectedRoles.includes(r.name);
                return (
                  <label
                    key={r.id}
                    className={`flex cursor-pointer items-center justify-between gap-3 rounded-xl border px-3.5 py-3 text-sm transition-colors ${
                      checked
                        ? 'border-primary/50 bg-primary/5'
                        : 'border-border hover:bg-accent/50'
                    }`}
                  >
                    <span className="font-medium">{r.display_name}</span>
                    <input
                      type="checkbox"
                      className="h-4 w-4 accent-primary"
                      checked={checked}
                      onChange={() => toggleRole(r.name)}
                    />
                  </label>
                );
              })}
            </div>
          )}
        </Panel>

        <div className="sticky bottom-4 z-10 flex items-center justify-between gap-3 rounded-2xl border border-border bg-background/90 px-4 py-3 shadow-soft backdrop-blur">
          <Button variant="outline" type="button" onClick={() => navigate(paths.users)}>
            <ArrowRight className="h-4 w-4 rtl:rotate-180" />
            {t('users.form.backToList')}
          </Button>
          <Button type="submit" disabled={saving}>
            <Save className="h-4 w-4" />
            {saving ? t('common.saving') : t('common.save')}
          </Button>
        </div>
      </form>
    </div>
  );
}
