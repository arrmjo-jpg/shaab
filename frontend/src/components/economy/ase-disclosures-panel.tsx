'use client';

import { FileArchive, FileText } from 'lucide-react';
import { useState } from 'react';

import type { AseDoc } from '@/lib/ase-market';

type Tab = 'disclosures' | 'circulars';

// التعاميم والإفصاحات — تبويبان (إفصاحات/تعاميم) + جدول تحميل (الوثيقة · التاريخ · PDF/ZIP).
// المصدر HTML الرسميّ من ASE (لا API)؛ روابط التحميل تفتح ملفّ ASE مباشرةً في تبويب جديد.
export function AseDisclosuresPanel({
  disclosures,
  circulars,
}: {
  disclosures: AseDoc[] | null;
  circulars: AseDoc[] | null;
}) {
  const [tab, setTab] = useState<Tab>('disclosures');
  const rows = tab === 'disclosures' ? disclosures : circulars;

  const TABS: { key: Tab; label: string }[] = [
    { key: 'disclosures', label: 'الإفصاحات' },
    { key: 'circulars', label: 'التعاميم' },
  ];

  return (
    <div>
      <div className="mb-4 flex flex-wrap gap-1 border-b border-border" role="tablist">
        {TABS.map((t) => (
          <button
            key={t.key}
            type="button"
            role="tab"
            aria-selected={tab === t.key}
            onClick={() => setTab(t.key)}
            className={
              'border-b-2 px-3 py-2 text-sm font-bold transition-colors ' +
              (tab === t.key ? 'border-primary text-primary' : 'border-transparent text-muted hover:text-fg')
            }
          >
            {t.label}
          </button>
        ))}
      </div>

      {rows && rows.length > 0 ? (
        <div className="max-h-[440px] overflow-y-auto border border-border">
          <table className="w-full border-collapse text-sm">
            <thead className="sticky top-0 z-10 bg-surface-2">
              <tr>
                <th className="px-3 py-2.5 text-start font-bold text-fg">الوثيقة</th>
                <th className="px-3 py-2.5 text-start font-bold text-fg whitespace-nowrap">التاريخ</th>
                <th className="px-3 py-2.5 text-center font-bold text-fg whitespace-nowrap">تحميل</th>
              </tr>
            </thead>
            <tbody>
              {rows.map((r, i) => (
                <tr key={`${r.name}-${i}`} className="border-t border-border hover:bg-surface-2">
                  <td className="px-3 py-2.5 text-fg">{r.name}</td>
                  <td className="px-3 py-2.5 whitespace-nowrap text-muted">{r.date}</td>
                  <td className="px-3 py-2.5">
                    <div className="flex items-center justify-center gap-2">
                      {r.pdfUrl && (
                        <a
                          href={r.pdfUrl}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-primary transition-opacity hover:opacity-70"
                          aria-label={`تحميل PDF: ${r.name}`}
                          title="PDF"
                        >
                          <FileText className="size-5" />
                        </a>
                      )}
                      {r.zipUrl && (
                        <a
                          href={r.zipUrl}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="text-amber-600 transition-opacity hover:opacity-70"
                          aria-label={`تحميل ZIP: ${r.name}`}
                          title="ZIP"
                        >
                          <FileArchive className="size-5" />
                        </a>
                      )}
                      {!r.pdfUrl && !r.zipUrl && <span className="text-xs text-muted">—</span>}
                    </div>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      ) : (
        <div className="flex min-h-[140px] items-center justify-center border border-dashed border-border text-sm text-muted">
          لا توجد {tab === 'disclosures' ? 'إفصاحات' : 'تعاميم'} متاحة حاليّاً.
        </div>
      )}
    </div>
  );
}
