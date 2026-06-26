import { useNavigate } from 'react-router-dom';
import { useTranslation } from 'react-i18next';
import { AlertTriangle, MoreHorizontal, Pencil, Plus, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { DataTable, type Column } from '@/components/data/DataTable';
import { paths } from '@/router/paths';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { useAdZones, useDeleteAdZone } from '../hooks';
import type { AdZoneData } from '@/types/advertising.types';

export default function AdZonesPage() {
  const { t } = useTranslation('advertising');
  const navigate = useNavigate();
  const { hasPermission } = useAuth();
  const { confirm } = useToast();
  const canManage = hasPermission('ad-zones.manage');

  const q = useAdZones();
  const del = useDeleteAdZone();
  const rows = q.data ?? [];

  const onDelete = async (z: AdZoneData) => {
    if (
      await confirm({
        title: t('zones.confirm.deleteTitle'),
        text: t('zones.confirm.deleteText', { name: z.name }),
        confirmText: t('zones.confirm.delete'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(z.id);
  };

  const columns: Column<AdZoneData>[] = [
    { key: 'key', header: t('zones.col.key'), render: (z) => <code className="text-xs">{z.key}</code> },
    { key: 'name', header: t('zones.col.name'), render: (z) => <span className="font-medium">{z.name}</span> },
    {
      key: 'type',
      header: t('zones.col.type'),
      render: (z) => (z.placement_type ? <Badge variant="muted">{t(`placementType.${z.placement_type}`)}</Badge> : '—'),
    },
    {
      key: 'strategy',
      header: t('zones.col.strategy'),
      render: (z) => (z.selector_strategy ? t(`selectorStrategy.${z.selector_strategy}`) : '—'),
    },
    {
      key: 'size',
      header: t('zones.col.size'),
      render: (z) => (z.width && z.height ? `${z.width}×${z.height}` : '—'),
    },
    {
      key: 'locale',
      header: t('zones.col.locale'),
      render: (z) => <Badge variant="muted">{z.locale ? z.locale.toUpperCase() : t('locale.all')}</Badge>,
    },
    {
      key: 'status',
      header: t('zones.col.status'),
      render: (z) => <Badge variant={z.is_active ? 'success' : 'muted'}>{t(z.is_active ? 'active' : 'inactive')}</Badge>,
    },
    {
      key: 'placements',
      header: t('zones.col.placements'),
      align: 'center',
      render: (z) => <span className="tabular-nums text-muted-foreground">{z.placements_count ?? 0}</span>,
    },
    ...(canManage
      ? [
          {
            key: 'actions',
            header: '',
            align: 'end',
            render: (z: AdZoneData) => (
              <DropdownMenu>
                <DropdownMenuTrigger asChild>
                  <Button variant="ghost" size="icon" className="h-8 w-8">
                    <MoreHorizontal className="h-4 w-4" />
                  </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                  <DropdownMenuItem onClick={() => navigate(paths.adZonesEdit.replace(':id', String(z.id)))}>
                    <Pencil className="h-4 w-4" />
                    {t('zones.action.edit')}
                  </DropdownMenuItem>
                  <DropdownMenuSeparator />
                  <DropdownMenuItem
                    onClick={() => void onDelete(z)}
                    className="text-destructive focus:text-destructive"
                  >
                    <Trash2 className="h-4 w-4" />
                    {t('zones.action.delete')}
                  </DropdownMenuItem>
                </DropdownMenuContent>
              </DropdownMenu>
            ),
          } as Column<AdZoneData>,
        ]
      : []),
  ];

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('zones.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('zones.subtitle')}</p>
        </div>
        {canManage ? (
          <Button onClick={() => navigate(paths.adZonesCreate)}>
            <Plus className="h-4 w-4" />
            {t('zones.new')}
          </Button>
        ) : null}
      </header>

      {q.isError ? (
        <div className="flex items-center justify-between gap-3 border border-destructive bg-destructive/5 p-4 text-sm">
          <span className="flex items-center gap-2 text-destructive">
            <AlertTriangle className="h-4 w-4" />
            {t('zones.error')}
          </span>
          <Button variant="outline" size="sm" onClick={() => void q.refetch()}>
            {t('zones.retry')}
          </Button>
        </div>
      ) : (
        <DataTable
          columns={columns}
          rows={rows}
          rowKey={(z) => z.id}
          loading={q.isLoading}
          emptyTitle={t('zones.empty.title')}
          emptyDescription={t('zones.empty.description')}
        />
      )}
    </div>
  );
}
