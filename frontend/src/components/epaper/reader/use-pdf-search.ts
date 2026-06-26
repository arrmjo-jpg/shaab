'use client';

import type { PDFDocumentProxy } from 'pdfjs-dist';
import { useCallback, useRef, useState } from 'react';

// بحث نصّيّ حقيقيّ داخل الـ PDF عبر طبقة النصّ (getTextContent) — لا اعتماد على الخلفيّة. يُخزِّن
// نصّ كلّ صفحة مرّةً (cache) فالبحث التالي فوريّ، ويُلغي البحث الجاري عند استعلامٍ أحدث.
export interface SearchHit {
  page: number;
  snippet: string;
}

function normalize(s: string): string {
  return s.replace(/\s+/g, ' ').toLowerCase();
}

export function usePdfSearch(doc: PDFDocumentProxy | null, numPages: number) {
  const [hits, setHits] = useState<SearchHit[]>([]);
  const [searching, setSearching] = useState(false);
  const [query, setQuery] = useState('');
  const textCache = useRef<Map<number, string>>(new Map());
  const runId = useRef(0);

  const pageText = useCallback(
    async (n: number): Promise<string> => {
      const cached = textCache.current.get(n);
      if (cached !== undefined) return cached;
      if (!doc) return '';
      const page = await doc.getPage(n);
      const content = await page.getTextContent();
      const text = content.items.map((it) => ('str' in it ? it.str : '')).join(' ');
      textCache.current.set(n, text);
      return text;
    },
    [doc],
  );

  const search = useCallback(
    async (raw: string) => {
      const term = raw.trim();
      setQuery(term);
      if (!doc || term.length < 2) {
        setHits([]);
        setSearching(false);
        return;
      }
      const id = ++runId.current;
      setSearching(true);
      const needle = normalize(term);
      const found: SearchHit[] = [];
      for (let n = 1; n <= numPages; n++) {
        if (id !== runId.current) return; // أُلغي ببحثٍ أحدث
        const text = await pageText(n);
        const hay = normalize(text);
        const idx = hay.indexOf(needle);
        if (idx >= 0) {
          const start = Math.max(0, idx - 30);
          const end = Math.min(hay.length, idx + needle.length + 30);
          found.push({ page: n, snippet: `…${text.slice(start, end).trim()}…` });
        }
      }
      if (id === runId.current) {
        setHits(found);
        setSearching(false);
      }
    },
    [doc, numPages, pageText],
  );

  const clear = useCallback(() => {
    runId.current++;
    setHits([]);
    setQuery('');
    setSearching(false);
  }, []);

  return { hits, searching, query, search, clear };
}
