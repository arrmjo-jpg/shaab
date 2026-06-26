'use client';

import { Loader2, Search, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import type { SearchHit } from './use-pdf-search';

interface ReaderSearchPanelProps {
  open: boolean;
  hits: SearchHit[];
  searching: boolean;
  query: string;
  currentPage: number;
  onSearch: (q: string) => void;
  onJump: (page: number) => void;
  onClose: () => void;
}

// لوحة البحث داخل العدد — تنزلق من جهة البداية (يمين RTL)، إدخال + قائمة نتائج (صفحة + مقتطف)؛
// نقر نتيجة ⇒ انتقال لصفحتها. تُركَّز تلقائيّاً عند الفتح (Ctrl+F).
export function ReaderSearchPanel({
  open,
  hits,
  searching,
  query,
  currentPage,
  onSearch,
  onJump,
  onClose,
}: ReaderSearchPanelProps) {
  const inputRef = useRef<HTMLInputElement>(null);
  const [value, setValue] = useState(query);

  useEffect(() => {
    if (open) inputRef.current?.focus();
  }, [open]);

  if (!open) return null;

  return (
    <aside
      dir="rtl"
      className="absolute inset-y-0 start-0 z-20 flex w-full max-w-xs flex-col border-e border-white/10 bg-[#1a1a1a]/95 text-neutral-200 backdrop-blur-sm"
      role="dialog"
      aria-label="بحث داخل العدد"
    >
      <form
        className="flex items-center gap-2 border-b border-white/10 p-2"
        onSubmit={(e) => {
          e.preventDefault();
          onSearch(value);
        }}
      >
        <Search className="size-4 shrink-0 text-neutral-400" aria-hidden />
        <input
          ref={inputRef}
          value={value}
          onChange={(e) => setValue(e.target.value)}
          placeholder="ابحث في نصّ العدد…"
          aria-label="كلمة البحث"
          className="h-9 min-w-0 flex-1 bg-transparent text-sm text-neutral-100 outline-none placeholder:text-neutral-500"
        />
        <button type="button" onClick={onClose} aria-label="إغلاق البحث" className="shrink-0 rounded-sm p-1 hover:bg-white/10">
          <X className="size-4" aria-hidden />
        </button>
      </form>

      <div className="flex items-center justify-between px-3 py-2 text-xs text-neutral-400">
        {searching ? (
          <span className="flex items-center gap-2">
            <Loader2 className="size-3.5 animate-spin" aria-hidden /> جارٍ البحث…
          </span>
        ) : query.length >= 2 ? (
          <span>النتائج: {hits.length}</span>
        ) : (
          <span>اكتب حرفين على الأقلّ</span>
        )}
      </div>

      <ul className="min-h-0 flex-1 overflow-y-auto">
        {hits.map((hit) => (
          <li key={hit.page}>
            <button
              type="button"
              onClick={() => onJump(hit.page)}
              aria-current={hit.page === currentPage ? 'true' : undefined}
              className={`block w-full border-b border-white/5 px-3 py-2 text-start transition-colors hover:bg-white/10 ${
                hit.page === currentPage ? 'bg-white/5' : ''
              }`}
            >
              <span className="block text-xs font-bold text-neutral-300">صفحة {hit.page}</span>
              <span className="mt-0.5 line-clamp-2 text-xs text-neutral-400">{hit.snippet}</span>
            </button>
          </li>
        ))}
        {!searching && query.length >= 2 && hits.length === 0 ? (
          <li className="px-3 py-6 text-center text-xs text-neutral-500">لا نتائج مطابقة في هذا العدد.</li>
        ) : null}
      </ul>
    </aside>
  );
}
