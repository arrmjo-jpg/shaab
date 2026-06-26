import { useEffect, useState } from 'react';
import { useTranslation } from 'react-i18next';
import { Modal } from '@/components/ui/modal';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { SelectField } from '@/components/form/SelectField';
import { useToast } from '@/hooks/useToast';
import { useImportWhatsappContacts, useWhatsappGroups } from '../hooks';
import type { WhatsappImportReport } from '@/types/whatsapp.types';

interface Props {
  open: boolean;
  onClose: () => void;
}

/**
 * استيراد جهات اتصال من CSV/XLSX — اختيار الملف + المجموعة الوجهة + سياسة التكرار،
 * ثم عرض تقرير النتائج (ناجح/محدَّث/متجاوَز/فاشل + أسباب). نمط مودال المشروع.
 */
export function WhatsappImportModal({ open, onClose }: Props) {
  const { t } = useTranslation('whatsapp');
  const { error: toastError } = useToast();
  const groupsQ = useWhatsappGroups();
  const importMut = useImportWhatsappContacts();

  const [file, setFile] = useState<File | null>(null);
  const [groupId, setGroupId] = useState('');
  const [duplicates, setDuplicates] = useState<'update' | 'skip'>('skip');
  const [report, setReport] = useState<WhatsappImportReport | null>(null);

  useEffect(() => {
    if (!open) return;
    setFile(null);
    setGroupId('');
    setDuplicates('skip');
    setReport(null);
  }, [open]);

  const submit = () => {
    if (!file) {
      toastError(t('import.fileRequired'));
      return;
    }
    if (groupId === '') {
      toastError(t('import.groupRequired'));
      return;
    }
    importMut.mutate(
      { file, group_id: Number(groupId), duplicates },
      { onSuccess: (r) => setReport(r) },
    );
  };

  const saving = importMut.isPending;
  const groups = groupsQ.data ?? [];

  return (
    <Modal
      open={open}
      onClose={onClose}
      title={t('import.title')}
      footer={
        <>
          <Button variant="outline" onClick={onClose} disabled={saving}>
            {report ? t('import.close') : t('common.cancel', { ns: 'common' })}
          </Button>
          {!report ? (
            <Button onClick={submit} disabled={saving}>
              {saving ? t('import.importing') : t('import.start')}
            </Button>
          ) : null}
        </>
      }
    >
      {!report ? (
        <div className="grid gap-4">
          <div>
            <Label htmlFor="wa-import-file">{t('import.file')}</Label>
            <input
              id="wa-import-file"
              type="file"
              accept=".csv,.xlsx,text/csv"
              onChange={(e) => setFile(e.target.files?.[0] ?? null)}
              className="block w-full border border-input bg-background px-3.5 py-2.5 text-sm file:me-3 file:border-0 file:bg-muted file:px-3 file:py-1"
            />
            <p className="mt-1 text-xs text-muted-foreground">{t('import.fileHint')}</p>
          </div>

          <SelectField
            label={t('import.group')}
            value={groupId}
            onChange={(e) => setGroupId(e.target.value)}
            options={[
              { value: '', label: t('import.groupPlaceholder') },
              ...groups.map((g) => ({ value: String(g.id), label: g.name })),
            ]}
          />

          <SelectField
            label={t('import.duplicates')}
            value={duplicates}
            onChange={(e) => setDuplicates(e.target.value as 'update' | 'skip')}
            options={[
              { value: 'skip', label: t('import.dupSkip') },
              { value: 'update', label: t('import.dupUpdate') },
            ]}
          />
        </div>
      ) : (
        <div className="grid gap-3">
          <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            <ReportStat label={t('import.report.created')} value={report.created} tone="success" />
            <ReportStat label={t('import.report.updated')} value={report.updated} tone="info" />
            <ReportStat label={t('import.report.skipped')} value={report.skipped} tone="muted" />
            <ReportStat label={t('import.report.failed')} value={report.failed} tone="danger" />
          </div>
          {report.errors.length > 0 ? (
            <div className="max-h-48 overflow-y-auto border border-border">
              <table className="w-full text-xs">
                <thead className="bg-muted text-muted-foreground">
                  <tr>
                    <th className="px-2 py-1 text-start">{t('import.report.row')}</th>
                    <th className="px-2 py-1 text-start">{t('import.report.value')}</th>
                    <th className="px-2 py-1 text-start">{t('import.report.reason')}</th>
                  </tr>
                </thead>
                <tbody>
                  {report.errors.map((er, i) => (
                    <tr key={i} className="border-t border-border">
                      <td className="px-2 py-1">{er.row}</td>
                      <td className="px-2 py-1" dir="ltr">{er.value ?? ''}</td>
                      <td className="px-2 py-1">{er.reason}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          ) : null}
        </div>
      )}
    </Modal>
  );
}

function ReportStat({ label, value, tone }: { label: string; value: number; tone: 'success' | 'info' | 'muted' | 'danger' }) {
  const toneCls = {
    success: 'text-emerald-600',
    info: 'text-sky-600',
    muted: 'text-muted-foreground',
    danger: 'text-destructive',
  }[tone];
  return (
    <div className="border border-border p-2 text-center">
      <p className={`text-lg font-bold ${toneCls}`}>{value}</p>
      <p className="text-xs text-muted-foreground">{label}</p>
    </div>
  );
}
