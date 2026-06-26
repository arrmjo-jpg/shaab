import { useMemo, useState, type ReactNode } from 'react';
import { useTranslation } from 'react-i18next';
import {
  Activity,
  Coins,
  Cpu,
  Info,
  RefreshCw,
  Sparkles,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { ErrorState, PageSkeleton } from '@/components/feedback';
import { useAiUsage } from '../hooks';
import type { AiUsageGroup, AiUsageQuery, AiUsageRow } from '@/types/ai.types';

const PROVIDERS = ['openai', 'gemini', 'none'] as const;
const ACTIONS = ['headlines', 'excerpt', 'rewrite', 'tags', 'seo', 'analyze'] as const;
const SOURCES = ['ai', 'auto'] as const;
const ACTION_LABEL_KEYS = new Set<string>(ACTIONS);

interface Filters {
  provider: string;
  action: string;
  source: string;
  from: string;
  to: string;
}

const EMPTY_FILTERS: Filters = { provider: '', action: '', source: '', from: '', to: '' };
const PER_PAGE = 20;

function StatCard({
  label,
  value,
  icon,
  hint,
}: {
  label: string;
  value: ReactNode;
  icon: ReactNode;
  hint?: string;
}) {
  return (
    <div className="flex items-center gap-4 rounded-2xl border border-border bg-background p-5 shadow-soft">
      <div className="shrink-0 text-primary">{icon}</div>
      <div className="min-w-0">
        <p className="text-xs text-muted-foreground">{label}</p>
        <p className="text-2xl font-bold tabular-nums">{value}</p>
        {hint ? <p className="mt-0.5 text-xs text-muted-foreground">{hint}</p> : null}
      </div>
    </div>
  );
}

export default function AiUsagePage() {
  const { t, i18n } = useTranslation('ai');
  const [page, setPage] = useState(1);
  const [draft, setDraft] = useState<Filters>(EMPTY_FILTERS);
  const [applied, setApplied] = useState<Filters>(EMPTY_FILTERS);

  const params = useMemo<AiUsageQuery>(() => {
    const p: AiUsageQuery = { page, per_page: PER_PAGE };
    if (applied.provider) p['filter[provider]'] = applied.provider;
    if (applied.action) p['filter[action]'] = applied.action;
    if (applied.source) p['filter[source]'] = applied.source;
    if (applied.from) p['filter[from]'] = applied.from;
    if (applied.to) p['filter[to]'] = applied.to;
    return p;
  }, [page, applied]);

  const q = useAiUsage(params);

  const nf = useMemo(() => new Intl.NumberFormat(i18n.language), [i18n.language]);
  const cf = useMemo(
    () =>
      new Intl.NumberFormat(i18n.language, {
        style: 'currency',
        currency: 'USD',
        maximumFractionDigits: 4,
      }),
    [i18n.language],
  );
  const dtf = useMemo(
    () => new Intl.DateTimeFormat(i18n.language, { dateStyle: 'short', timeStyle: 'short' }),
    [i18n.language],
  );

  const actionLabel = (a: string) => (ACTION_LABEL_KEYS.has(a) ? t(`usage.action.${a}`) : a);
  const sourceBadge = (s: string) =>
    s === 'ai' ? (
      <Badge variant="default">{t('usage.source.ai')}</Badge>
    ) : (
      <Badge variant="muted">{t('usage.source.auto')}</Badge>
    );

  const applyFilters = () => {
    setPage(1);
    setApplied(draft);
  };
  const resetFilters = () => {
    setPage(1);
    setDraft(EMPTY_FILTERS);
    setApplied(EMPTY_FILTERS);
  };

  if (q.isLoading && !q.data) return <PageSkeleton />;
  if (q.isError || !q.data) return <ErrorState onRetry={() => void q.refetch()} />;

  const { rows, meta } = q.data;
  const { totals, caps, by_provider, by_action, trend } = meta;

  const remainingText = (cap: number, remaining: number | null, used: number) =>
    cap > 0
      ? `${t('usage.budget.remaining')}: ${nf.format(remaining ?? 0)} · ${t('usage.budget.used')}: ${nf.format(used)} ${t('usage.budget.ofCap', { cap: nf.format(cap) })}`
      : t('usage.budget.unlimited');

  const budgetRemainingText =
    caps.monthly_budget_usd > 0
      ? `${t('usage.budget.remaining')}: ${cf.format(caps.remaining.monthly_budget_usd ?? 0)} · ${t('usage.budget.used')}: ${cf.format(totals.month.estimated_cost)} ${t('usage.budget.ofCap', { cap: cf.format(caps.monthly_budget_usd) })}`
      : t('usage.budget.unlimited');

  const maxTrend = Math.max(1, ...trend.map((p) => p.requests));

  const columns: Column<AiUsageRow>[] = [
    {
      key: 'user',
      header: t('usage.col.user'),
      render: (r) => (
        <span className="font-medium">{r.user?.name ?? t('usage.system')}</span>
      ),
    },
    {
      key: 'provider',
      header: t('usage.col.provider'),
      render: (r) => <span dir="ltr">{r.provider}</span>,
    },
    { key: 'action', header: t('usage.col.action'), render: (r) => actionLabel(r.action) },
    { key: 'source', header: t('usage.col.source'), render: (r) => sourceBadge(r.source) },
    {
      key: 'tokens',
      header: t('usage.col.tokens'),
      align: 'end',
      render: (r) => <span className="tabular-nums">{nf.format(r.tokens)}</span>,
    },
    {
      key: 'cost',
      header: t('usage.col.cost'),
      align: 'end',
      render: (r) => <span className="tabular-nums">{cf.format(r.estimated_cost)}</span>,
    },
    {
      key: 'at',
      header: t('usage.col.at'),
      render: (r) => (
        <span className="whitespace-nowrap text-sm text-muted-foreground" dir="ltr">
          {r.created_at ? dtf.format(new Date(r.created_at)) : '—'}
        </span>
      ),
    },
  ];

  const Breakdown = ({ title, groups }: { title: string; groups: AiUsageGroup[] }) => (
    <div className="rounded-2xl border border-border bg-background p-5 shadow-soft">
      <h3 className="text-sm font-semibold text-muted-foreground">{title}</h3>
      {groups.length === 0 ? (
        <p className="mt-3 text-sm text-muted-foreground">{t('usage.breakdown.noData')}</p>
      ) : (
        <ul className="mt-3 space-y-2">
          {groups.map((g) => (
            <li key={g.label} className="flex items-center justify-between gap-3 text-sm">
              <span className="font-medium" dir="ltr">
                {ACTION_LABEL_KEYS.has(g.label) ? actionLabel(g.label) : g.label}
              </span>
              <span className="flex items-center gap-3 text-muted-foreground">
                <span className="tabular-nums">
                  {nf.format(g.requests)} {t('usage.breakdown.requests')}
                </span>
                <span className="tabular-nums">{cf.format(g.estimated_cost)}</span>
              </span>
            </li>
          ))}
        </ul>
      )}
    </div>
  );

  return (
    <div className="space-y-6">
      <header className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">{t('usage.title')}</h1>
          <p className="mt-1 text-sm text-muted-foreground">{t('usage.subtitle')}</p>
        </div>
        <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
          <RefreshCw className={q.isFetching ? 'h-4 w-4 animate-spin' : 'h-4 w-4'} />
          {t('usage.refresh')}
        </Button>
      </header>

      <p className="flex items-center gap-2 rounded-xl border border-border bg-muted/40 px-4 py-2.5 text-xs text-muted-foreground">
        <Info className="h-4 w-4 shrink-0" />
        {t('usage.estimateNote')}
      </p>

      {/* الإجماليات */}
      <section className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        <StatCard
          label={`${t('usage.totals.requests')} · ${t('usage.totals.today')}`}
          value={nf.format(totals.today.requests)}
          icon={<Activity className="h-7 w-7" />}
          hint={`${t('usage.totals.month')}: ${nf.format(totals.month.requests)}`}
        />
        <StatCard
          label={`${t('usage.totals.tokens')} · ${t('usage.totals.month')}`}
          value={nf.format(totals.month.tokens)}
          icon={<Cpu className="h-7 w-7" />}
          hint={`${t('usage.totals.today')}: ${nf.format(totals.today.tokens)}`}
        />
        <StatCard
          label={`${t('usage.totals.cost')} · ${t('usage.totals.month')}`}
          value={cf.format(totals.month.estimated_cost)}
          icon={<Coins className="h-7 w-7" />}
          hint={`${t('usage.totals.today')}: ${cf.format(totals.today.estimated_cost)}`}
        />
      </section>

      {/* الحدود والمتبقّي */}
      <section className="space-y-3">
        <h2 className="text-sm font-semibold text-muted-foreground">{t('usage.budget.title')}</h2>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          <StatCard
            label={t('usage.budget.dailyRequests')}
            value={caps.daily_requests > 0 ? nf.format(caps.daily_requests) : t('usage.budget.unlimited')}
            icon={<Sparkles className="h-7 w-7" />}
            hint={remainingText(caps.daily_requests, caps.remaining.daily_requests, totals.today.requests)}
          />
          <StatCard
            label={t('usage.budget.monthlyRequests')}
            value={caps.monthly_requests > 0 ? nf.format(caps.monthly_requests) : t('usage.budget.unlimited')}
            icon={<Sparkles className="h-7 w-7" />}
            hint={remainingText(caps.monthly_requests, caps.remaining.monthly_requests, totals.month.requests)}
          />
          <StatCard
            label={t('usage.budget.monthlyBudget')}
            value={caps.monthly_budget_usd > 0 ? cf.format(caps.monthly_budget_usd) : t('usage.budget.unlimited')}
            icon={<Coins className="h-7 w-7" />}
            hint={budgetRemainingText}
          />
        </div>
      </section>

      {/* التوزيع */}
      <section className="grid gap-4 lg:grid-cols-2">
        <Breakdown title={t('usage.breakdown.byProvider')} groups={by_provider} />
        <Breakdown title={t('usage.breakdown.byAction')} groups={by_action} />
      </section>

      {/* الاتجاه */}
      <section className="rounded-2xl border border-border bg-background p-5 shadow-soft">
        <h2 className="text-sm font-semibold text-muted-foreground">{t('usage.trend.title')}</h2>
        {trend.length === 0 ? (
          <p className="mt-3 text-sm text-muted-foreground">{t('usage.trend.empty')}</p>
        ) : (
          <div className="mt-4 flex h-32 items-end gap-1 overflow-x-auto" dir="ltr">
            {trend.map((p) => (
              <div
                key={p.day}
                className="flex min-w-[8px] flex-1 flex-col items-center justify-end"
                title={`${p.day} · ${nf.format(p.requests)} ${t('usage.trend.requests')} · ${cf.format(p.estimated_cost)}`}
              >
                <div
                  className="w-full rounded-t bg-primary/70 transition-[height]"
                  style={{ height: `${Math.max(4, (p.requests / maxTrend) * 100)}%` }}
                />
              </div>
            ))}
          </div>
        )}
      </section>

      {/* المرشّحات */}
      <section className="rounded-2xl border border-border bg-background p-5 shadow-soft">
        <h2 className="mb-3 text-sm font-semibold text-muted-foreground">{t('usage.filters.title')}</h2>
        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
          <label className="space-y-1.5 text-sm">
            <span className="text-xs text-muted-foreground">{t('usage.filters.provider')}</span>
            <select
              className="flex h-11 w-full rounded-xl border border-input bg-background px-3.5 text-sm"
              value={draft.provider}
              onChange={(e) => setDraft((d) => ({ ...d, provider: e.target.value }))}
            >
              <option value="">{t('usage.filters.all')}</option>
              {PROVIDERS.map((p) => (
                <option key={p} value={p}>
                  {p}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1.5 text-sm">
            <span className="text-xs text-muted-foreground">{t('usage.filters.action')}</span>
            <select
              className="flex h-11 w-full rounded-xl border border-input bg-background px-3.5 text-sm"
              value={draft.action}
              onChange={(e) => setDraft((d) => ({ ...d, action: e.target.value }))}
            >
              <option value="">{t('usage.filters.all')}</option>
              {ACTIONS.map((a) => (
                <option key={a} value={a}>
                  {actionLabel(a)}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1.5 text-sm">
            <span className="text-xs text-muted-foreground">{t('usage.filters.source')}</span>
            <select
              className="flex h-11 w-full rounded-xl border border-input bg-background px-3.5 text-sm"
              value={draft.source}
              onChange={(e) => setDraft((d) => ({ ...d, source: e.target.value }))}
            >
              <option value="">{t('usage.filters.all')}</option>
              {SOURCES.map((s) => (
                <option key={s} value={s}>
                  {t(`usage.source.${s}`)}
                </option>
              ))}
            </select>
          </label>
          <label className="space-y-1.5 text-sm">
            <span className="text-xs text-muted-foreground">{t('usage.filters.from')}</span>
            <Input
              type="date"
              value={draft.from}
              onChange={(e) => setDraft((d) => ({ ...d, from: e.target.value }))}
            />
          </label>
          <label className="space-y-1.5 text-sm">
            <span className="text-xs text-muted-foreground">{t('usage.filters.to')}</span>
            <Input
              type="date"
              value={draft.to}
              onChange={(e) => setDraft((d) => ({ ...d, to: e.target.value }))}
            />
          </label>
        </div>
        <div className="mt-3 flex items-center gap-2">
          <Button variant="default" size="sm" onClick={applyFilters}>
            {t('usage.filters.apply')}
          </Button>
          <Button variant="ghost" size="sm" onClick={resetFilters}>
            {t('usage.filters.reset')}
          </Button>
        </div>
      </section>

      {/* الجدول */}
      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(r) => r.id}
        loading={q.isFetching && rows.length === 0}
        emptyTitle={t('usage.empty')}
      />

      <Pagination meta={meta.pagination} onPage={(p) => setPage(p)} />
    </div>
  );
}
