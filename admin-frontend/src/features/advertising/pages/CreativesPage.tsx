import { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, ArchiveRestore, Code2, Image as ImageIcon, MoreHorizontal, Pencil, Plus, Trash2, X } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/data/DataTable';
import { Pagination } from '@/components/data/Pagination';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import {
  useAdCampaigns,
  useAdCreatives,
  useDeleteAdCreative,
  useForceDeleteAdCreative,
  useRestoreAdCreative,
} from '../hooks';
import {
  AD_CREATIVE_TYPES_SELECTABLE,
  type AdCreativeData,
  type AdCreativesListParams,
} from '@/types/advertising.types';

const selectCls =
  'h-10 border border-input bg-background px-3 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring';
const PER_PAGE = 15;

function fmtDate(iso: string | null, locale: string): string {
  if (!iso) return '—';
  return new Date(iso).toLocaleDateString(locale, { year: 'numeric', month: 'short', day: 'numeric' });
}

export default function CreativesPage() {
  const { t, i18n } = useTranslation('advertising');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { confirm } = useToast();

  const canCreate = hasPermission('ads.create');
  const canEdit = hasPermission('ads.edit');
  const canDelete = hasPermission('ads.delete');
  const canRestore = hasPermission('ads.restore');
  const canForceDelete = hasPermission('ads.force_delete');
  const canSeeTrash = canRestore || canForceDelete;

  const [params, setParams] = useState<AdCreativesListParams>({
    page: 1,
    per_page: PER_PAGE,
    search: '',
    ad_campaign_id: '',
    type: '',
    is_active: '',
    sort: '-created_at',
    trashed: '',
  });

  const q = useAdCreatives(params);
  const campaignsQ = useAdCampaigns({ page: 1, per_page: 100, search: '', status: '', pacing_mode: '', sort: 'name', trashed: '' });
  const del = useDeleteAdCreative();
  const restore = useRestoreAdCreative();
  const forceDel = useForceDeleteAdCreative();

  const inTrash = params.trashed === 'only';
  const rows = q.data?.data ?? [];
  const campaigns = campaignsQ.data?.data ?? [];
  const patch = (p: Partial<AdCreativesListParams>) => setParams((prev) => ({ ...prev, ...p, page: p.page ?? 1 }));

  const onDelete = async (c: AdCreativeData) => {
    if (
      await confirm({
        title: t('creatives.confirm.deleteTitle'),
        text: t('creatives.confirm.deleteText', { title: c.title }),
        confirmText: t('creatives.confirm.delete'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(c.id);
  };
  const onForceDelete = async (c: AdCreativeData) => {
    if (
      await confirm({
        title: t('creatives.confirm.forceTitle'),
        text: t('creatives.confirm.forceText', { title: c.title }),
        confirmText: t('creatives.confirm.forceYes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      forceDel.mutate(c.id);
  };

  const columns: Column<AdCreativeData>[] = [
    {
      key: 'thumb',
      header: '',
      render: (c) => (
        <div className="flex h-12 w-16 items-center justify-center overflow-hidden border border-border bg-muted">
          {c.media?.url ? (
            <img src={c.media.url} alt="" className="h-full w-full object-cover" />
          ) : c.type === 'html' ? (
            <Code2 className="h-4 w-4 text-muted-foreground" />
          ) : (
            <ImageIcon className="h-4 w-4 text-muted-foreground" />
          )}
        </div>
      ),
    },
    {
      key: 'title',
      header: t('creatives.col.title'),
      render: (c) => (
        <div className="min-w-0">
          <p className="truncate font-medium">{c.title}</p>
          {c.type ? <Badge variant="muted">{t(`creativeType.${c.type}`)}</Badge> : null}
        </div>
      ),
    },
    { key: 'campaign', header: t('creatives.col.campaign'), render: (c) => <span className="text-sm text-muted-foreground">{c.campaign?.name ?? '—'}</span> },
    { key: 'weight', header: t('creatives.col.weight'), align: 'center', render: (c) => <span className="tabular-nums text-muted-foreground">{c.weight}</span> },
    { key: 'status', header: t('creatives.col.status'), render: (c) => <Badge variant={c.is_active ? 'success' : 'muted'}>{t(c.is_active ? 'active' : 'inactive')}</Badge> },
    { key: 'placements', header: t('creatives.col.placements'), align: 'center', render: (c) => <span className="tabular-nums text-muted-foreground">{c.placements_count ?? 0}</span> },
    { key: 'date', header: t('creatives.col.date'), render: (c) => <span className="whitespace-nowrap text-xs text-muted-foreground">{fmtDate(c.created_at, i18n.language)}</span> },
    {
      key: 'actions',
      header: '',
      align: 'end',
      render: (c) => (
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <Button variant="ghost" size="icon" className="h-8 w-8">
              <MoreHorizontal className="h-4 w-4" />
            </Button>
          </DropdownMenuTrigger>
          <DropdownMenuContent align="end">
            {inTrash ? (
              <>
                {canRestore ? (
                  <DropdownMenuItem onClick={() => restore.mutate(c.id)}>
                    <ArchiveRestore className="h-4 w-4" />
                    {t('creatives.action.restore')}
                  </DropdownMenuItem>
                ) : null}
                {canForceDelete ? (
                  <DropdownMenuItem onClick={() => void onForceDelete(c)} className="text-destructive focus:text-destructive">
                    <Trash2 className="h-4 w-4" />
                    {t('creatives.action.forceDelete')}
                  </DropdownMenuItem>
                ) : null}
              </>
            ) : (
              <>
                {canEdit ? (
                  <DropdownMenuItem onClick={() => navigate(paths.adCreativesEdit.replace(':id', String(c.id)))}>
                    <Pencil className="h-4 w-4" />
                    {t('creatives.action.edit')}
                  </DropdownMenuItem>
                ) : null}
                {canDelete ? (
                  <>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem onClick={() => void onDelete(c)} className="text-destructive focus:text-destructive">
                      <Trash2 className="h-4 w-4" />
                      {t('creatives.action.delete')}
                    </DropdownMenuItem>
                  </>
                ) : null}
              </>
            )}
          </DropdownMenuContent>
        </DropdownMenu>
      ),
    },
  ];

  const hasFilters = Boolean(params.search || params.ad_campaign_id || params.type || params.is_active || params.trashed);

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('creatives.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('creatives.subtitle')}</p>
        </div>
        {canCreate ? (
          <Button onClick={() => navigate(paths.adCreativesCreate)}>
            <Plus className="h-4 w-4" />
            {t('creatives.new')}
          </Button>
        ) : null}
      </header>

      <div className="flex flex-wrap items-center gap-3 border border-border bg-background p-3">
        <Input value={params.search} onChange={(e) => patch({ search: e.target.value })} placeholder={t('creatives.filter.search')} className="min-w-[200px] flex-1" />
        <select className={selectCls} value={params.ad_campaign_id} onChange={(e) => patch({ ad_campaign_id: e.target.value })}>
          <option value="">{t('creatives.filter.campaignAll')}</option>
          {campaigns.map((c) => (
            <option key={c.id} value={c.id}>
              {c.name}
            </option>
          ))}
        </select>
        <select className={selectCls} value={params.type} onChange={(e) => patch({ type: e.target.value as AdCreativesListParams['type'] })}>
          <option value="">{t('creatives.filter.typeAll')}</option>
          {AD_CREATIVE_TYPES_SELECTABLE.map((ty) => (
            <option key={ty} value={ty}>
              {t(`creativeType.${ty}`)}
            </option>
          ))}
        </select>
        <select className={selectCls} value={params.is_active} onChange={(e) => patch({ is_active: e.target.value as AdCreativesListParams['is_active'] })}>
          <option value="">{t('creatives.filter.activeAll')}</option>
          <option value="1">{t('creatives.filter.activeOnly')}</option>
          <option value="0">{t('creatives.filter.inactiveOnly')}</option>
        </select>
        {canSeeTrash ? (
          <select className={selectCls} value={params.trashed} onChange={(e) => patch({ trashed: e.target.value as AdCreativesListParams['trashed'] })}>
            <option value="">{t('creatives.filter.trashedNone')}</option>
            <option value="only">{t('creatives.filter.trashedOnly')}</option>
          </select>
        ) : null}
        {hasFilters ? (
          <Button variant="outline" size="sm" onClick={() => patch({ search: '', ad_campaign_id: '', type: '', is_active: '', trashed: '' })}>
            <X className="h-4 w-4" />
            {t('creatives.filter.reset')}
          </Button>
        ) : null}
      </div>

      {q.isError ? (
        <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
          <span className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-4 w-4" />
            {t('creatives.error')}
          </span>
          <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
            {t('creatives.retry')}
          </Button>
        </div>
      ) : (
        <>
          <DataTable
            columns={columns}
            rows={rows}
            rowKey={(c) => c.id}
            loading={q.isLoading}
            emptyTitle={inTrash ? t('creatives.empty.trashTitle') : t('creatives.empty.title')}
            emptyDescription={inTrash ? t('creatives.empty.trashDescription') : t('creatives.empty.description')}
          />
          {q.data ? <Pagination meta={q.data.pagination} onPage={(page) => patch({ page })} /> : null}
        </>
      )}
    </div>
  );
}
