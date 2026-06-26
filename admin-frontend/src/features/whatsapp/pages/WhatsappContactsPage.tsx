import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { ChevronLeft, ChevronRight, Download, Pencil, Plus, Search, Trash2, Upload } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { DataTable, type Column } from '@/components/data/DataTable';
import { ErrorState } from '@/components/feedback';
import { SelectField } from '@/components/form/SelectField';
import { useAuth } from '@/hooks/useAuth';
import { useToast } from '@/hooks/useToast';
import { whatsappService } from '@/services/whatsapp.service';
import { useDeleteWhatsappContact, useWhatsappContacts, useWhatsappGroups } from '../hooks';
import { WhatsappContactFormModal } from '../components/WhatsappContactFormModal';
import { WhatsappImportModal } from '../components/WhatsappImportModal';
import type { WhatsappContactData, WhatsappContactsListParams } from '@/types/whatsapp.types';

const DEFAULT_PARAMS: WhatsappContactsListParams = {
  page: 1,
  per_page: 15,
  q: '',
  group_id: '',
  status: '',
};

/** جهات اتصال واتساب — بحث (اسم/هاتف) + فلترة (مجموعة/حالة) + ترقيم + CRUD بمودال. */
export default function WhatsappContactsPage() {
  const { t } = useTranslation('whatsapp');
  const { hasPermission } = useAuth();
  const { confirm, error: toastError } = useToast();

  const canManage = hasPermission('whatsapp.manage');
  const canImport = hasPermission('whatsapp.import');
  const canExport = hasPermission('whatsapp.export');

  const [params, setParams] = useState<WhatsappContactsListParams>(DEFAULT_PARAMS);
  const [searchInput, setSearchInput] = useState('');
  const [modalOpen, setModalOpen] = useState(false);
  const [importOpen, setImportOpen] = useState(false);
  const [exporting, setExporting] = useState(false);
  const [editing, setEditing] = useState<WhatsappContactData | null>(null);

  const onExport = async (format: 'csv' | 'xlsx') => {
    setExporting(true);
    try {
      const blob = await whatsappService.exportContacts(
        { q: params.q, group_id: params.group_id, status: params.status },
        format,
      );
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `whatsapp-contacts.${format}`;
      a.click();
      URL.revokeObjectURL(url);
    } catch {
      toastError(t('export.failed'));
    } finally {
      setExporting(false);
    }
  };

  const groupsQ = useWhatsappGroups();
  const q = useWhatsappContacts(params);
  const del = useDeleteWhatsappContact();

  const rows = q.data?.data ?? [];
  const pagination = q.data?.pagination ?? null;

  const applySearch = () => setParams((p) => ({ ...p, q: searchInput, page: 1 }));

  const openCreate = () => {
    setEditing(null);
    setModalOpen(true);
  };
  const openEdit = (c: WhatsappContactData) => {
    setEditing(c);
    setModalOpen(true);
  };
  const onDelete = async (c: WhatsappContactData) => {
    if (
      await confirm({
        title: t('contacts.confirm.deleteTitle'),
        text: t('contacts.confirm.deleteText', { name: c.name }),
        confirmText: t('contacts.confirm.yes'),
        cancelText: t('common.cancel', { ns: 'common' }),
      })
    )
      del.mutate(c.id);
  };

  const columns: Column<WhatsappContactData>[] = [
    {
      key: 'name',
      header: t('contacts.col.name'),
      render: (c) => <p className="truncate font-medium">{c.name}</p>,
    },
    {
      key: 'phone',
      header: t('contacts.col.phone'),
      render: (c) => (
        <span dir="ltr" className="font-mono text-sm">
          {c.phone}
        </span>
      ),
    },
    {
      key: 'groups',
      header: t('contacts.col.groups'),
      render: (c) => (
        <div className="flex flex-wrap gap-1">
          {c.groups.map((g) => (
            <Badge key={g.id} variant="muted">
              {g.name}
            </Badge>
          ))}
        </div>
      ),
    },
    {
      key: 'status',
      header: t('contacts.col.status'),
      render: (c) =>
        c.status === 'subscribed' ? (
          <Badge variant="success">{t('contacts.status.subscribed')}</Badge>
        ) : (
          <Badge variant="muted">{t('contacts.status.unsubscribed')}</Badge>
        ),
    },
    {
      key: 'actions',
      header: '',
      align: 'end',
      render: (c) =>
        canManage ? (
          <div className="flex items-center justify-end gap-1">
            <Button variant="ghost" size="icon" aria-label={t('contacts.edit')} onClick={() => openEdit(c)}>
              <Pencil className="h-4 w-4" />
            </Button>
            <Button variant="ghost" size="icon" aria-label={t('contacts.delete')} onClick={() => void onDelete(c)}>
              <Trash2 className="h-4 w-4 text-destructive" />
            </Button>
          </div>
        ) : null,
    },
  ];

  if (q.isError) return <ErrorState onRetry={() => void q.refetch()} />;

  const groups = groupsQ.data ?? [];

  return (
    <div className="space-y-6">
      <header className="flex flex-wrap items-center justify-between gap-3">
        <div>
          <h1 className="text-2xl font-bold">{t('contacts.title')}</h1>
          <p className="text-sm text-muted-foreground">{t('contacts.subtitle')}</p>
        </div>
        <div className="flex flex-wrap items-center gap-2">
          {canExport ? (
            <>
              <Button variant="outline" disabled={exporting} onClick={() => void onExport('csv')}>
                <Download className="h-4 w-4" />
                {t('export.csv')}
              </Button>
              <Button variant="outline" disabled={exporting} onClick={() => void onExport('xlsx')}>
                <Download className="h-4 w-4" />
                {t('export.xlsx')}
              </Button>
            </>
          ) : null}
          {canImport ? (
            <Button variant="outline" onClick={() => setImportOpen(true)}>
              <Upload className="h-4 w-4" />
              {t('import.button')}
            </Button>
          ) : null}
          {canManage ? (
            <Button onClick={openCreate}>
              <Plus className="h-4 w-4" />
              {t('contacts.create')}
            </Button>
          ) : null}
        </div>
      </header>

      {/* البحث والتصفية */}
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
        <div className="flex gap-2">
          <Input
            value={searchInput}
            onChange={(e) => setSearchInput(e.target.value)}
            onKeyDown={(e) => {
              if (e.key === 'Enter') applySearch();
            }}
            placeholder={t('contacts.searchPlaceholder')}
          />
          <Button variant="outline" size="icon" aria-label={t('contacts.search')} onClick={applySearch}>
            <Search className="h-4 w-4" />
          </Button>
        </div>
        <SelectField
          label=""
          aria-label={t('contacts.filterGroup')}
          value={params.group_id === '' ? '' : String(params.group_id)}
          onChange={(e) =>
            setParams((p) => ({
              ...p,
              group_id: e.target.value === '' ? '' : Number(e.target.value),
              page: 1,
            }))
          }
          options={[
            { value: '', label: t('contacts.allGroups') },
            ...groups.map((g) => ({ value: String(g.id), label: g.name })),
          ]}
        />
        <SelectField
          label=""
          aria-label={t('contacts.filterStatus')}
          value={params.status}
          onChange={(e) =>
            setParams((p) => ({ ...p, status: e.target.value as typeof p.status, page: 1 }))
          }
          options={[
            { value: '', label: t('contacts.allStatuses') },
            { value: 'subscribed', label: t('contacts.status.subscribed') },
            { value: 'unsubscribed', label: t('contacts.status.unsubscribed') },
          ]}
        />
      </div>

      <DataTable
        columns={columns}
        rows={rows}
        rowKey={(c) => c.id}
        loading={q.isLoading}
        emptyTitle={t('contacts.empty')}
      />

      {/* ترقيم بسيط */}
      {pagination && pagination.total_pages > 1 ? (
        <div className="flex items-center justify-between text-sm text-muted-foreground">
          <span>{t('contacts.pageInfo', { page: pagination.current_page, pages: pagination.total_pages, total: pagination.total })}</span>
          <div className="flex gap-2">
            <Button
              variant="outline"
              size="icon"
              aria-label={t('contacts.prev')}
              disabled={pagination.current_page <= 1}
              onClick={() => setParams((p) => ({ ...p, page: p.page - 1 }))}
            >
              <ChevronRight className="h-4 w-4" />
            </Button>
            <Button
              variant="outline"
              size="icon"
              aria-label={t('contacts.next')}
              disabled={pagination.current_page >= pagination.total_pages}
              onClick={() => setParams((p) => ({ ...p, page: p.page + 1 }))}
            >
              <ChevronLeft className="h-4 w-4" />
            </Button>
          </div>
        </div>
      ) : null}

      <WhatsappContactFormModal open={modalOpen} onClose={() => setModalOpen(false)} contact={editing} />
      <WhatsappImportModal open={importOpen} onClose={() => setImportOpen(false)} />
    </div>
  );
}
