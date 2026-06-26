'use client';

import type { PDFDocumentProxy } from 'pdfjs-dist';
import { useEffect, useState } from 'react';

import { loadPdfjs, PDF_CMAP_URL, PDF_STANDARD_FONTS_URL } from '@/lib/pdf/pdfjs';

export interface PdfDocState {
  doc: PDFDocumentProxy | null;
  numPages: number;
  /** أبعاد الصفحة الأولى عند scale=1 — تُستخدَم لحجز ارتفاع كلّ صفحة (افتراض تجانس صفحات الجريدة)
   *  فلا نحمّل كلّ الصفحات لمعرفة الأبعاد. تُضبَط الصفحة فعليّاً عند رسمها. */
  baseWidth: number;
  baseHeight: number;
  status: 'loading' | 'ready' | 'error';
}

const INITIAL: PdfDocState = { doc: null, numPages: 0, baseWidth: 0, baseHeight: 0, status: 'loading' };

/** يحمّل وثيقة PDF من مصدر نفس‑الأصل (وكيل الـ BFF) عبر pdf.js؛ يُتلِف الوثيقة عند التفكيك. */
export function usePdfDocument(src: string): PdfDocState {
  const [state, setState] = useState<PdfDocState>(INITIAL);

  useEffect(() => {
    let cancelled = false;
    let opened: PDFDocumentProxy | null = null;
    setState(INITIAL);

    void (async () => {
      try {
        const pdfjs = await loadPdfjs();
        const task = pdfjs.getDocument({
          url: src,
          cMapUrl: PDF_CMAP_URL,
          cMapPacked: true,
          standardFontDataUrl: PDF_STANDARD_FONTS_URL,
        });
        const doc = await task.promise;
        opened = doc;
        if (cancelled) {
          void doc.destroy();
          return;
        }
        const first = await doc.getPage(1);
        const viewport = first.getViewport({ scale: 1 });
        if (cancelled) {
          void doc.destroy();
          return;
        }
        setState({
          doc,
          numPages: doc.numPages,
          baseWidth: viewport.width,
          baseHeight: viewport.height,
          status: 'ready',
        });
      } catch {
        if (!cancelled) setState((s) => ({ ...s, status: 'error' }));
      }
    })();

    return () => {
      cancelled = true;
      if (opened) void opened.destroy();
    };
  }, [src]);

  return state;
}
