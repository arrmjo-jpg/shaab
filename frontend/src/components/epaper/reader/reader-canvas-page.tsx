'use client';

import type { PDFDocumentProxy, RenderTask } from 'pdfjs-dist';
import { useEffect, useRef, useState } from 'react';

interface ReaderCanvasPageProps {
  doc: PDFDocumentProxy;
  pageNumber: number;
  scale: number; // المقياس الفعليّ (تكبير × ملاءمة)
  rotation: number; // 0 | 90 | 180 | 270
  cssWidth: number; // عرض الإطار (CSS px) — يحجز التخطيط فلا اهتزاز
  cssHeight: number;
  render: boolean; // داخل نافذة العرض (الحاليّة ± شعاع)؛ خارجها يُفرَّغ من الذاكرة (تخلية)
}

// صفحة واحدة مُحاكاة افتراضيّاً: تُرسَم إلى canvas فقط داخل النافذة (يقودها المنسّق = الحاليّة ±2)،
// وتُفرَّغ خارجها (تصفير الـcanvas يحرّر الـbitmap) ⇒ ذاكرة محدودة مهما كثُرت الصفحات. هيكل عظميّ
// (skeleton) أثناء التحميل/التخلية فلا شاشة بيضاء. حادّة عبر devicePixelRatio (سقف 2).
export function ReaderCanvasPage({
  doc,
  pageNumber,
  scale,
  rotation,
  cssWidth,
  cssHeight,
  render,
}: ReaderCanvasPageProps) {
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const taskRef = useRef<RenderTask | null>(null);
  const [drawn, setDrawn] = useState(false);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;

    if (!render) {
      taskRef.current?.cancel();
      taskRef.current = null;
      canvas.width = 0;
      canvas.height = 0;
      setDrawn(false);
      return;
    }

    let disposed = false;
    setDrawn(false);
    void (async () => {
      try {
        const page = await doc.getPage(pageNumber);
        if (disposed) return;
        // دقّة رسم عالية (oversampling): تُرسَم الصفحة بـ~3× أبعاد العرض فتبدو حادّة كالـPDF لا كصورة
        // باهتة — حتّى على شاشات 1× (حيث كان min(dpr,2)=1 يُنعّم خطّ الجريدة الدقيق). يُعاد الرسم عند
        // التكبير فيبقى حادّاً، مع سقف أبعاد (MAX_DIM) يكبح الذاكرة عند التكبير العالي + رسم الزوج
        // الحاليّ فقط ⇒ ذاكرة محدودة رغم الجودة.
        const MAX_DIM = 4500;
        const displayVp = page.getViewport({ scale, rotation });
        const dpr = Math.max(
          1,
          Math.min(Math.max(window.devicePixelRatio || 1, 2) * 1.5, 3, MAX_DIM / Math.max(displayVp.width, displayVp.height)),
        );
        const device = page.getViewport({ scale: scale * dpr, rotation });
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        taskRef.current?.cancel();
        canvas.width = Math.floor(device.width);
        canvas.height = Math.floor(device.height);
        canvas.style.width = `${cssWidth}px`;
        canvas.style.height = `${cssHeight}px`;
        const task = page.render({ canvas, canvasContext: ctx, viewport: device });
        taskRef.current = task;
        await task.promise;
        if (!disposed) setDrawn(true);
      } catch {
        // إلغاء الرسم (تغيّر المقياس/التخلية) — يُتجاهَل بأمان.
      }
    })();

    return () => {
      disposed = true;
    };
  }, [render, doc, pageNumber, scale, rotation, cssWidth, cssHeight]);

  useEffect(() => () => taskRef.current?.cancel(), []);

  return (
    <div
      data-page={pageNumber}
      className="relative shrink-0 transform-gpu bg-white shadow-[0_2px_24px_rgba(0,0,0,0.55)] [contain:content]"
      style={{ width: cssWidth, height: cssHeight }}
      role="img"
      aria-label={`صفحة ${pageNumber}`}
    >
      <canvas ref={canvasRef} className="block" />
      {!drawn ? (
        <div
          className="absolute inset-0 animate-pulse bg-gradient-to-b from-neutral-100 via-neutral-50 to-neutral-200"
          aria-hidden
        >
          <span className="absolute inset-x-0 bottom-3 text-center text-xs text-neutral-400">{pageNumber}</span>
        </div>
      ) : null}
    </div>
  );
}
