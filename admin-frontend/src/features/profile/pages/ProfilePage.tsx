import * as React from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { useTranslation } from 'react-i18next';
import {
  User as UserIcon,
  Lock,
  Activity as ActivityIcon,
  MonitorSmartphone,
  Camera,
  Loader2,
  Save,
  ShieldCheck,
  ShieldAlert,
  Trash2,
  LogIn,
  LogOut,
  KeyRound,
  Mail,
  Pencil,
  CalendarDays,
  Clock,
  FileText,
  Newspaper,
  Clapperboard,
  Images,
  Eye,
  Sparkles,
  Coins,
  Cpu,
  LayoutDashboard,
  BarChart3,
  ShieldQuestion,
  Settings as SettingsIcon,
  Server,
  CheckCircle2,
  XCircle,
  type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import { storageUrl } from '@/lib/storage';
import { DEFAULT_AVATAR } from '@/lib/constants';
import { Button } from '@/components/ui/button';
import { Badge } from '@/components/ui/badge';
import { TextField } from '@/components/form/TextField';
import { TextareaField } from '@/components/form/TextareaField';
import { PasswordField } from '@/components/form/PasswordField';
import { Label } from '@/components/ui/label';
import { PageSkeleton, ErrorState, EmptyState, LoadingState } from '@/components/feedback';
import { Pagination } from '@/components/data/Pagination';
import { useToast } from '@/hooks/useToast';
import {
  profileInfoSchema,
  passwordSchema,
  type ProfileInfoValues,
  type PasswordValues,
} from '../schemas';
import {
  useProfile,
  useUpdateProfile,
  useChangePassword,
  useProfileActivity,
  useProfileAnalytics,
  useProfilePermissions,
  useProfileSecurity,
  useSessions,
  useRevokeSession,
  useRevokeOtherSessions,
  useUploadProfileAvatar,
} from '../hooks';
import type { ProfileData, ProfileSession } from '@/types/profile.types';

const SOCIALS = ['facebook', 'twitter_x', 'instagram', 'linkedin', 'youtube'] as const;
type Tab = 'overview' | 'analytics' | 'activity' | 'security' | 'permissions' | 'edit';

const EVENT_ICON: Record<string, LucideIcon> = {
  admin_login: LogIn,
  password_changed: KeyRound,
  password_reset_requested: Mail,
  profile_updated: Pencil,
  sessions_revoked_others: LogOut,
  user_roles_updated: ShieldCheck,
  role_permissions_updated: ShieldCheck,
  cache_cleared: Server,
  settings_updated: SettingsIcon,
};

const ACTIVITY_FILTERS = ['', 'auth', 'rbac', 'settings', 'system', 'user'] as const;

// ─── shared bits ────────────────────────────────────────────────────────────

function SectionHead({
  icon: Icon,
  tone,
  title,
  hint,
}: {
  icon: LucideIcon;
  tone: string;
  title: string;
  hint?: string;
}) {
  return (
    <div className="mb-5 flex items-center gap-3">
      <div className={cn('flex h-10 w-10 shrink-0 items-center justify-center rounded-xl', tone)}>
        <Icon className="h-5 w-5" />
      </div>
      <div>
        <h3 className="text-sm font-bold">{title}</h3>
        {hint ? <p className="text-xs text-muted-foreground">{hint}</p> : null}
      </div>
    </div>
  );
}

function Metric({
  icon: Icon,
  label,
  value,
  tone = 'primary',
}: {
  icon: LucideIcon;
  label: string;
  value: React.ReactNode;
  tone?: 'primary' | 'emerald' | 'amber';
}) {
  const toneClass =
    tone === 'emerald'
      ? 'text-emerald-600 dark:text-emerald-400'
      : tone === 'amber'
        ? 'text-amber-600 dark:text-amber-400'
        : 'text-primary';
  return (
    <div className="flex items-center gap-4 rounded-2xl border border-border bg-background p-5 shadow-soft">
      <div className={cn('shrink-0', toneClass)}>
        <Icon className="h-7 w-7" />
      </div>
      <div className="min-w-0">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="text-2xl font-bold tabular-nums">{value}</p>
      </div>
    </div>
  );
}

function HeroStat({ icon: Icon, label, value }: { icon: LucideIcon; label: string; value: string }) {
  return (
    <div className="flex items-center gap-2.5 rounded-xl border border-border bg-muted/30 px-3.5 py-2">
      <Icon className="h-4 w-4 shrink-0 text-muted-foreground" />
      <div className="min-w-0">
        <p className="text-[11px] leading-tight text-muted-foreground">{label}</p>
        <p className="truncate text-xs font-semibold" dir="auto">
          {value}
        </p>
      </div>
    </div>
  );
}

function Row({ label, value }: { label: string; value: React.ReactNode }) {
  return (
    <div className="flex items-center justify-between gap-4 border-b border-border py-2.5 last:border-0">
      <span className="text-sm text-muted-foreground">{label}</span>
      <span className="text-sm font-medium" dir="auto">
        {value}
      </span>
    </div>
  );
}

function useFmt() {
  const { i18n } = useTranslation();
  return React.useMemo(
    () => ({
      date: (v: string | null) =>
        v ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium' }).format(new Date(v)) : null,
      dateTime: (v: string | null) =>
        v
          ? new Intl.DateTimeFormat(i18n.language, { dateStyle: 'medium', timeStyle: 'short' }).format(
              new Date(v),
            )
          : null,
      num: (n: number) => new Intl.NumberFormat(i18n.language).format(n),
      cost: (n: number) =>
        new Intl.NumberFormat(i18n.language, {
          style: 'currency',
          currency: 'USD',
          maximumFractionDigits: 4,
        }).format(n),
    }),
    [i18n.language],
  );
}

function relativeTime(iso: string | null, lang: string): string {
  if (!iso) return '';
  const diff = Date.now() - new Date(iso).getTime();
  const rtf = new Intl.RelativeTimeFormat(lang, { numeric: 'auto' });
  const min = Math.round(diff / 60000);
  if (Math.abs(min) < 60) return rtf.format(-min, 'minute');
  const hr = Math.round(min / 60);
  if (Math.abs(hr) < 24) return rtf.format(-hr, 'hour');
  return rtf.format(-Math.round(hr / 24), 'day');
}

function statusVariant(status: string): 'success' | 'destructive' | 'muted' {
  if (status === 'active') return 'success';
  if (status === 'suspended' || status === 'banned') return 'destructive';
  return 'muted';
}

// ─── page ─────────────────────────────────────────────────────────────────

export default function ProfilePage() {
  const { t } = useTranslation('profile');
  const fmt = useFmt();
  const [tab, setTab] = React.useState<Tab>('overview');

  const q = useProfile();
  const update = useUpdateProfile();
  const uploadAvatar = useUploadProfileAvatar();
  const avatarInput = React.useRef<HTMLInputElement>(null);

  if (q.isLoading) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const p = q.data;
  const avatarPreview = p.avatar ? storageUrl(p.avatar) ?? DEFAULT_AVATAR : DEFAULT_AVATAR;

  const pickAvatar = (file?: File) => {
    if (!file) return;
    uploadAvatar.mutate(file, {
      onSuccess: (r) =>
        update.mutate({
          name: p.name,
          bio: p.bio ?? null,
          avatar: r.path,
          social_links: p.social_links ?? {},
        }),
    });
  };

  const tabs: { key: Tab; icon: LucideIcon }[] = [
    { key: 'overview', icon: LayoutDashboard },
    { key: 'analytics', icon: BarChart3 },
    { key: 'activity', icon: ActivityIcon },
    { key: 'security', icon: ShieldCheck },
    { key: 'permissions', icon: ShieldQuestion },
    { key: 'edit', icon: Pencil },
  ];

  return (
    <div className="space-y-6">
      {/* Hero */}
      <div className="relative overflow-hidden rounded-2xl border border-border bg-background">
        <div className="h-28 bg-gradient-to-l from-primary/25 via-primary/10 to-transparent" />
        <input
          ref={avatarInput}
          type="file"
          accept="image/jpeg,image/png,image/webp"
          className="hidden"
          onChange={(e) => pickAvatar(e.target.files?.[0])}
        />
        <div className="-mt-14 flex flex-col gap-5 px-6 pb-6 lg:flex-row lg:items-end lg:justify-between">
          {/* Identity group */}
          <div className="flex min-w-0 items-end gap-4">
            <button
              type="button"
              onClick={() => avatarInput.current?.click()}
              className="group relative h-28 w-28 shrink-0 overflow-hidden rounded-2xl border-4 border-background bg-background shadow-soft"
              aria-label={t('info.avatarChoose')}
            >
              <img src={avatarPreview} alt="" className="h-full w-full object-cover" />
              <span className="absolute inset-0 flex items-center justify-center bg-foreground/45 opacity-0 transition-opacity group-hover:opacity-100">
                {uploadAvatar.isPending || update.isPending ? (
                  <Loader2 className="h-6 w-6 animate-spin text-white" />
                ) : (
                  <Camera className="h-6 w-6 text-white" />
                )}
              </span>
            </button>
            <div className="min-w-0 pb-1">
              <h1 className="truncate text-2xl font-bold">{p.name}</h1>
              <p className="mt-0.5 truncate text-sm text-muted-foreground" dir="ltr">
                {p.email}
              </p>
              <div className="mt-2.5 flex flex-wrap gap-1.5">
                {p.roles.map((r) => (
                  <Badge key={r.name}>{r.display_name}</Badge>
                ))}
                {p.is_writer ? <Badge variant="muted">{t('meta.writer')}</Badge> : null}
                <Badge variant={statusVariant(p.status)}>{p.status_label}</Badge>
                <Badge variant={p.email_verified ? 'success' : 'destructive'}>
                  {p.email_verified ? t('meta.verified') : t('meta.unverified')}
                </Badge>
              </div>
            </div>
          </div>

          {/* Meta stat pills */}
          <div className="flex flex-wrap gap-2.5 lg:pb-1">
            <HeroStat icon={CalendarDays} label={t('meta.joined')} value={fmt.date(p.created_at) ?? t('meta.none')} />
            <HeroStat icon={Clock} label={t('meta.lastLogin')} value={fmt.dateTime(p.last_login_at) ?? t('meta.none')} />
          </div>
        </div>
      </div>

      {/* Tabs + content */}
      <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
        <nav className="flex gap-1 overflow-x-auto rounded-2xl border border-border bg-background p-2 lg:sticky lg:top-20 lg:h-fit lg:flex-col lg:overflow-visible">
          {tabs.map(({ key, icon: Icon }) => (
            <button
              key={key}
              type="button"
              onClick={() => setTab(key)}
              className={cn(
                'flex items-center gap-3 whitespace-nowrap rounded-xl px-3.5 py-2.5 text-sm font-medium transition-colors',
                tab === key
                  ? 'bg-primary/10 text-primary'
                  : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
              )}
            >
              <Icon className="h-4 w-4" />
              {t(`tabs.${key}`)}
            </button>
          ))}
        </nav>

        <div className="min-w-0">
          {tab === 'overview' ? <OverviewTab p={p} /> : null}
          {tab === 'analytics' ? <AnalyticsTab /> : null}
          {tab === 'activity' ? <ActivityTab /> : null}
          {tab === 'security' ? <SecurityTab /> : null}
          {tab === 'permissions' ? <PermissionsTab /> : null}
          {tab === 'edit' ? <EditTab p={p} /> : null}
        </div>
      </div>
    </div>
  );
}

// ─── Overview ───────────────────────────────────────────────────────────────

function OverviewTab({ p }: { p: ProfileData }) {
  const { t } = useTranslation('profile');
  const fmt = useFmt();
  const analytics = useProfileAnalytics();
  const perms = useProfilePermissions();

  const socialEntries = Object.entries(p.social_links ?? {}).filter(([, v]) => v);

  return (
    <div className="space-y-6">
      <div className="grid gap-6 lg:grid-cols-2">
        {/* Identity */}
        <div className="rounded-2xl border border-border bg-background p-6">
          <SectionHead icon={UserIcon} tone="bg-primary/10 text-primary" title={t('overview.identity')} />
          <Row label={t('meta.email')} value={<span dir="ltr">{p.email}</span>} />
          <Row label={t('meta.status')} value={<Badge variant={statusVariant(p.status)}>{p.status_label}</Badge>} />
          <Row
            label={t('meta.verified')}
            value={
              <Badge variant={p.email_verified ? 'success' : 'destructive'}>
                {p.email_verified ? t('security.verified') : t('security.unverified')}
              </Badge>
            }
          />
          <Row label={t('meta.joined')} value={fmt.date(p.created_at)} />
          <Row label={t('meta.lastLogin')} value={fmt.dateTime(p.last_login_at) ?? t('meta.none')} />
          {p.last_login_ip ? <Row label={t('overview.ip')} value={<span dir="ltr">{p.last_login_ip}</span>} /> : null}
        </div>

        {/* Professional details */}
        <div className="rounded-2xl border border-border bg-background p-6">
          <SectionHead icon={FileText} tone="bg-primary/10 text-primary" title={t('overview.details')} />
          <p className="text-sm leading-7 text-muted-foreground">
            {p.bio?.trim() ? p.bio : t('overview.noBio')}
          </p>
          {socialEntries.length > 0 ? (
            <div className="mt-4 flex flex-wrap gap-2">
              {socialEntries.map(([k, v]) => (
                <a
                  key={k}
                  href={v}
                  target="_blank"
                  rel="noreferrer"
                  className="rounded-xl border border-border px-3 py-1.5 text-xs font-medium text-primary hover:bg-accent"
                  dir="ltr"
                >
                  {t(`info.soc.${k}`, k)}
                </a>
              ))}
            </div>
          ) : null}
        </div>
      </div>

      {/* Permissions summary */}
      <div className="rounded-2xl border border-border bg-background p-6">
        <SectionHead
          icon={ShieldQuestion}
          tone="bg-primary/10 text-primary"
          title={t('overview.permissionsSummary')}
        />
        {perms.isLoading ? (
          <LoadingState />
        ) : perms.data ? (
          <div className="flex flex-wrap items-center gap-2">
            {perms.data.roles.map((r) => (
              <Badge key={r.name}>{r.display_name}</Badge>
            ))}
            <span className="text-sm text-muted-foreground">
              {perms.data.is_super_admin
                ? t('overview.fullAccess')
                : t('overview.capabilities', {
                    permissions: perms.data.summary.permissions_count,
                    groups: perms.data.summary.groups_count,
                  })}
            </span>
          </div>
        ) : null}
      </div>

      {/* Quick stats */}
      <div>
        <h2 className="mb-3 text-sm font-semibold text-muted-foreground">{t('overview.quickStats')}</h2>
        {analytics.isLoading ? (
          <LoadingState />
        ) : analytics.data ? (
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <Metric icon={FileText} label={t('analytics.articlesCreated')} value={fmt.num(analytics.data.articles.created)} />
            <Metric icon={Clapperboard} label={t('analytics.reelsCreated')} value={fmt.num(analytics.data.reels.created)} />
            <Metric icon={Images} label={t('analytics.mediaUploads')} value={fmt.num(analytics.data.media.uploads)} />
            <Metric icon={Sparkles} label={t('analytics.aiRequests')} value={fmt.num(analytics.data.ai.requests)} tone="emerald" />
          </div>
        ) : null}
      </div>
    </div>
  );
}

// ─── Analytics ────────────────────────────────────────────────────────────

function AnalyticsTab() {
  const { t } = useTranslation('profile');
  const fmt = useFmt();
  const q = useProfileAnalytics();

  if (q.isError) return <ErrorState onRetry={() => void q.refetch()} />;
  if (q.isLoading || !q.data) return <LoadingState />;
  const d = q.data;

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-border bg-background p-6">
        <SectionHead icon={BarChart3} tone="bg-primary/10 text-primary" title={t('analytics.section')} hint={t('analytics.hint')} />
        <h3 className="mb-3 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
          {t('analytics.content')}
        </h3>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          <Metric icon={FileText} label={t('analytics.articlesCreated')} value={fmt.num(d.articles.created)} />
          <Metric icon={Newspaper} label={t('analytics.articlesPublished')} value={fmt.num(d.articles.published)} tone="emerald" />
          <Metric icon={Pencil} label={t('analytics.articlesDrafts')} value={fmt.num(d.articles.drafts)} tone="amber" />
          <Metric icon={Eye} label={t('analytics.viewsGenerated')} value={fmt.num(d.articles.views_generated)} />
          <Metric icon={Clapperboard} label={t('analytics.reelsCreated')} value={fmt.num(d.reels.created)} />
          <Metric icon={Clapperboard} label={t('analytics.reelsPublished')} value={fmt.num(d.reels.published)} tone="emerald" />
          <Metric icon={Pencil} label={t('analytics.reelsDrafts')} value={fmt.num(d.reels.drafts)} tone="amber" />
          <Metric icon={Images} label={t('analytics.mediaUploads')} value={fmt.num(d.media.uploads)} />
        </div>
      </div>

      <div className="rounded-2xl border border-border bg-background p-6">
        <SectionHead
          icon={Sparkles}
          tone="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
          title={t('analytics.ai')}
          hint={t('analytics.estimateNote')}
        />
        <div className="grid gap-4 sm:grid-cols-3">
          <Metric icon={Sparkles} label={t('analytics.aiRequests')} value={fmt.num(d.ai.requests)} tone="emerald" />
          <Metric icon={Cpu} label={t('analytics.aiTokens')} value={fmt.num(d.ai.tokens)} />
          <Metric icon={Coins} label={t('analytics.aiCost')} value={fmt.cost(d.ai.estimated_cost)} />
        </div>
      </div>
    </div>
  );
}

// ─── Activity ───────────────────────────────────────────────────────────────

function ActivityTab() {
  const { t, i18n } = useTranslation('profile');
  const [page, setPage] = React.useState(1);
  const [logName, setLogName] = React.useState<string>('');
  const q = useProfileActivity({
    page,
    ...(logName ? { 'filter[log_name]': logName } : {}),
  });

  const rows = q.data?.data ?? [];

  return (
    <div className="space-y-4 rounded-2xl border border-border bg-background p-6">
      <SectionHead icon={ActivityIcon} tone="bg-primary/10 text-primary" title={t('activity.section')} hint={t('activity.hint')} />

      {/* filter chips */}
      <div className="flex flex-wrap gap-2">
        {ACTIVITY_FILTERS.map((f) => (
          <button
            key={f || 'all'}
            type="button"
            onClick={() => {
              setLogName(f);
              setPage(1);
            }}
            className={cn(
              'rounded-full border px-3 py-1 text-xs font-medium transition-colors',
              logName === f
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-border text-muted-foreground hover:bg-accent',
            )}
          >
            {f ? t(`activity.filter.${f}`, f) : t('activity.filterAll')}
          </button>
        ))}
      </div>

      {q.isError ? (
        <ErrorState onRetry={() => void q.refetch()} />
      ) : q.isLoading && rows.length === 0 ? (
        <LoadingState />
      ) : rows.length === 0 ? (
        <EmptyState title={t('activity.empty')} />
      ) : (
        <ol className="relative space-y-5 ps-6">
          <span className="absolute inset-y-1 start-[11px] w-px bg-border" aria-hidden />
          {rows.map((a) => {
            const Icon = EVENT_ICON[a.event ?? ''] ?? ActivityIcon;
            return (
              <li key={a.id} className="relative">
                <span className="absolute -start-6 top-0 flex h-6 w-6 items-center justify-center rounded-full border border-border bg-background text-primary">
                  <Icon className="h-3.5 w-3.5" />
                </span>
                <div className="rounded-xl border border-border bg-muted/30 px-4 py-3">
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm font-medium">{a.description ?? a.event}</p>
                    <span className="text-xs text-muted-foreground" title={a.created_at ?? ''}>
                      {relativeTime(a.created_at, i18n.language)}
                    </span>
                  </div>
                  <div className="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground">
                    {a.log_name ? <Badge variant="muted">{t(`activity.filter.${a.log_name}`, a.log_name)}</Badge> : null}
                    {a.context.source ? (
                      <span>
                        {t('activity.source')}: {String(a.context.source)}
                      </span>
                    ) : null}
                    {a.context.ip ? <span dir="ltr">IP: {String(a.context.ip)}</span> : null}
                  </div>
                </div>
              </li>
            );
          })}
        </ol>
      )}
      {q.data && rows.length > 0 ? <Pagination meta={q.data.pagination} onPage={setPage} /> : null}
    </div>
  );
}

// ─── Security ───────────────────────────────────────────────────────────────

function SecurityTab() {
  const { t } = useTranslation('profile');
  const fmt = useFmt();
  const { confirm } = useToast();
  const security = useProfileSecurity();
  const changePw = useChangePassword();
  const revokeAll = useRevokeOtherSessions();

  const pwForm = useForm<PasswordValues>({
    resolver: zodResolver(passwordSchema),
    defaultValues: { current_password: '', password: '', password_confirmation: '' },
  });

  const savePw = pwForm.handleSubmit((v) =>
    changePw.mutate(
      {
        current_password: v.current_password,
        password: v.password,
        password_confirmation: v.password_confirmation,
      },
      { onSuccess: () => pwForm.reset() },
    ),
  );

  const onRevokeAll = async () => {
    if (
      await confirm({
        title: t('security.revokeAllConfirmTitle'),
        text: t('security.revokeAllConfirmText'),
        confirmText: t('security.revokeAllYes'),
        cancelText: t('sessions.revoke'),
      })
    )
      revokeAll.mutate();
  };

  const s = security.data;

  return (
    <div className="space-y-6">
      {/* Security summary */}
      <div className="rounded-2xl border border-border bg-background p-6">
        <SectionHead
          icon={ShieldCheck}
          tone="bg-emerald-500/10 text-emerald-600 dark:text-emerald-400"
          title={t('security.summary')}
          hint={t('security.hint')}
        />
        {security.isError ? (
          <ErrorState onRetry={() => void security.refetch()} />
        ) : security.isLoading || !s ? (
          <LoadingState />
        ) : (
          <div className="grid gap-x-8 sm:grid-cols-2">
            <Row
              label={t('security.emailVerified')}
              value={
                s.email_verified ? (
                  <span className="inline-flex items-center gap-1.5 text-emerald-600 dark:text-emerald-400">
                    <CheckCircle2 className="h-4 w-4" />
                    {t('security.verified')}
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1.5 text-destructive">
                    <XCircle className="h-4 w-4" />
                    {t('security.unverified')}
                  </span>
                )
              }
            />
            <Row label={t('security.passwordChanged')} value={fmt.dateTime(s.password_changed_at) ?? t('security.never')} />
            <Row label={t('security.lastLogin')} value={fmt.dateTime(s.last_login_at) ?? t('security.never')} />
            <Row label={t('security.lastLoginIp')} value={s.last_login_ip ? <span dir="ltr">{s.last_login_ip}</span> : t('meta.none')} />
            <Row label={t('security.resetRequests')} value={fmt.num(s.reset_requests_count)} />
            <Row label={t('security.lastReset')} value={fmt.dateTime(s.last_reset_requested_at) ?? t('security.never')} />
            <Row label={t('security.activeSessions')} value={fmt.num(s.active_sessions_count)} />
            <Row label={t('security.accountCreated')} value={fmt.date(s.account_created_at)} />
          </div>
        )}
      </div>

      {/* Change password */}
      <form onSubmit={savePw} className="space-y-5 rounded-2xl border border-border bg-background p-6" noValidate>
        <SectionHead icon={Lock} tone="bg-destructive/10 text-destructive" title={t('password.section')} hint={t('password.sectionHint')} />
        <PasswordField label={t('password.current')} error={pwForm.formState.errors.current_password} {...pwForm.register('current_password')} />
        <div className="grid gap-4 sm:grid-cols-2">
          <PasswordField label={t('password.new')} error={pwForm.formState.errors.password} {...pwForm.register('password')} />
          <PasswordField label={t('password.confirm')} error={pwForm.formState.errors.password_confirmation} {...pwForm.register('password_confirmation')} />
        </div>
        <div className="flex justify-end border-t border-border pt-4">
          <Button type="submit" disabled={changePw.isPending}>
            <Lock className="h-4 w-4" />
            {changePw.isPending ? t('password.saving') : t('password.save')}
          </Button>
        </div>
      </form>

      {/* Sessions */}
      <SessionsBlock onRevokeAll={() => void onRevokeAll()} revokingAll={revokeAll.isPending} />
    </div>
  );
}

function SessionsBlock({ onRevokeAll, revokingAll }: { onRevokeAll: () => void; revokingAll: boolean }) {
  const { t } = useTranslation('profile');
  const fmt = useFmt();
  const { confirm } = useToast();
  const q = useSessions();
  const revoke = useRevokeSession();

  const onRevoke = async (s: ProfileSession) => {
    if (
      await confirm({
        title: t('sessions.confirmTitle'),
        text: t('sessions.confirmText'),
        confirmText: t('sessions.yes'),
        cancelText: t('sessions.revoke'),
      })
    )
      revoke.mutate(s.id);
  };

  const sessions = q.data ?? [];
  const hasOthers = sessions.some((s) => !s.current);

  return (
    <div className="space-y-4 rounded-2xl border border-border bg-background p-6">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <SectionHead icon={MonitorSmartphone} tone="bg-primary/10 text-primary" title={t('sessions.section')} hint={t('sessions.hint')} />
        {hasOthers ? (
          <Button variant="outline" size="sm" disabled={revokingAll} onClick={onRevokeAll}>
            <ShieldAlert className={revokingAll ? 'h-4 w-4 animate-pulse' : 'h-4 w-4'} />
            {t('security.revokeAll')}
          </Button>
        ) : null}
      </div>
      {q.isError ? (
        <ErrorState onRetry={() => void q.refetch()} />
      ) : q.isLoading ? (
        <LoadingState />
      ) : sessions.length === 0 ? (
        <EmptyState title={t('sessions.empty')} />
      ) : (
        <div className="grid gap-3 sm:grid-cols-2">
          {sessions.map((s) => (
            <div
              key={s.id}
              className={cn(
                'flex items-start justify-between gap-3 rounded-xl border px-4 py-4',
                s.current ? 'border-primary/40 bg-primary/5' : 'border-border',
              )}
            >
              <div className="flex min-w-0 gap-3">
                <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-muted">
                  <MonitorSmartphone className="h-5 w-5 text-muted-foreground" />
                </div>
                <div className="min-w-0">
                  <p className="flex items-center gap-2 font-medium">
                    <span className="truncate">{s.name}</span>
                    {s.current ? <Badge variant="success">{t('sessions.current')}</Badge> : null}
                  </p>
                  <p className="mt-0.5 text-xs text-muted-foreground">
                    {t('sessions.lastUsed')}: {fmt.dateTime(s.last_used_at) ?? '—'}
                  </p>
                  <p className="text-xs text-muted-foreground">
                    {t('sessions.created')}: {fmt.dateTime(s.created_at) ?? '—'}
                  </p>
                </div>
              </div>
              {!s.current ? (
                <Button
                  type="button"
                  variant="ghost"
                  size="icon"
                  className="h-8 w-8 text-destructive"
                  disabled={revoke.isPending}
                  onClick={() => void onRevoke(s)}
                  aria-label={t('sessions.revoke')}
                >
                  <Trash2 className="h-4 w-4" />
                </Button>
              ) : null}
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ─── Permissions ─────────────────────────────────────────────────────────

function PermissionsTab() {
  const { t } = useTranslation('profile');
  const fmt = useFmt();
  const q = useProfilePermissions();

  if (q.isError) return <ErrorState onRetry={() => void q.refetch()} />;
  if (q.isLoading || !q.data) return <LoadingState />;
  const d = q.data;

  return (
    <div className="space-y-6">
      <div className="rounded-2xl border border-border bg-background p-6">
        <SectionHead icon={ShieldQuestion} tone="bg-primary/10 text-primary" title={t('permissions.section')} hint={t('permissions.hint')} />
        <div className="mb-4 flex flex-wrap gap-1.5">
          {d.roles.map((r) => (
            <Badge key={r.name}>{r.display_name}</Badge>
          ))}
        </div>
        <div className="grid gap-4 sm:grid-cols-3">
          <Metric icon={ShieldCheck} label={t('permissions.rolesCount')} value={fmt.num(d.summary.roles_count)} />
          <Metric icon={KeyRound} label={t('permissions.permissionsCount')} value={fmt.num(d.summary.permissions_count)} />
          <Metric icon={LayoutDashboard} label={t('permissions.groupsCount')} value={fmt.num(d.summary.groups_count)} />
        </div>
        {d.is_super_admin ? (
          <p className="mt-4 flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/5 px-4 py-3 text-sm font-medium text-emerald-600 dark:text-emerald-400">
            <ShieldCheck className="h-4 w-4" />
            {t('permissions.fullAccess')}
          </p>
        ) : null}
      </div>

      {d.groups.length === 0 ? (
        <EmptyState title={t('permissions.empty')} />
      ) : (
        <div className="grid gap-4 lg:grid-cols-2">
          {d.groups.map((g) => (
            <div key={g.group} className="rounded-2xl border border-border bg-background p-5">
              <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-semibold">{g.group}</h3>
                <Badge variant="muted">{fmt.num(g.count)}</Badge>
              </div>
              <div className="flex flex-wrap gap-1.5">
                {g.permissions.map((perm) => (
                  <span key={perm.name} className="rounded-lg bg-muted px-2.5 py-1 text-xs text-muted-foreground">
                    {perm.display_name}
                  </span>
                ))}
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
}

// ─── Edit profile ───────────────────────────────────────────────────────────

function EditTab({ p }: { p: ProfileData }) {
  const { t } = useTranslation('profile');
  const update = useUpdateProfile();

  const infoForm = useForm<ProfileInfoValues>({
    resolver: zodResolver(profileInfoSchema),
    values: {
      name: p.name ?? '',
      bio: p.bio ?? '',
      avatar: p.avatar ?? '',
      socials: {
        facebook: p.social_links?.facebook ?? '',
        twitter_x: p.social_links?.twitter_x ?? '',
        instagram: p.social_links?.instagram ?? '',
        linkedin: p.social_links?.linkedin ?? '',
        youtube: p.social_links?.youtube ?? '',
      },
    },
  });

  const saveInfo = infoForm.handleSubmit((v) => {
    const social_links: Record<string, string> = {};
    (Object.entries(v.socials) as [string, string | undefined][]).forEach(([k, val]) => {
      if (val) social_links[k] = val;
    });
    update.mutate({ name: v.name, bio: v.bio || null, avatar: v.avatar || null, social_links });
  });

  const removeAvatar = () =>
    update.mutate({ name: p.name, bio: p.bio ?? null, avatar: null, social_links: p.social_links ?? {} });

  return (
    <form onSubmit={saveInfo} className="space-y-5 rounded-2xl border border-border bg-background p-6" noValidate>
      <SectionHead icon={UserIcon} tone="bg-primary/10 text-primary" title={t('info.section')} hint={t('info.sectionHint')} />
      <TextField label={t('info.name')} error={infoForm.formState.errors.name} {...infoForm.register('name')} />

      {/* Email — intentionally restricted (security) */}
      <div className="space-y-1.5">
        <Label>{t('meta.email')}</Label>
        <input
          dir="ltr"
          value={p.email}
          disabled
          className="flex h-11 w-full cursor-not-allowed rounded-xl border border-input bg-muted/40 px-3.5 text-sm text-muted-foreground"
        />
        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
          <Mail className="h-3.5 w-3.5" />
          {t('info.emailRestricted')}
        </p>
      </div>

      <TextareaField label={t('info.bio')} {...infoForm.register('bio')} />

      {p.avatar ? (
        <div>
          <Button type="button" variant="ghost" size="sm" className="text-destructive" disabled={update.isPending} onClick={removeAvatar}>
            <Trash2 className="h-4 w-4" />
            {t('info.avatarRemove')}
          </Button>
        </div>
      ) : null}

      <div className="space-y-2">
        <Label>{t('info.social')}</Label>
        <div className="grid gap-3 sm:grid-cols-2">
          {SOCIALS.map((soc) => (
            <input
              key={soc}
              dir="ltr"
              placeholder={t(`info.soc.${soc}`)}
              className="flex h-11 w-full rounded-xl border border-input bg-background px-3.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
              {...infoForm.register(`socials.${soc}` as const)}
            />
          ))}
        </div>
      </div>

      <div className="flex justify-end border-t border-border pt-4">
        <Button type="submit" disabled={update.isPending}>
          <Save className="h-4 w-4" />
          {update.isPending ? t('info.saving') : t('info.save')}
        </Button>
      </div>
    </form>
  );
}
