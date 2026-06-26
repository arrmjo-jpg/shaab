'use client';

import type { PDFDocumentProxy, RenderTask } from 'pdfjs-dist';
import { useEffect, useRef, useState } from 'react';

type Orientation = 'horizontal' | 'vertical';

function ReaderThumb({
  doc,
  pageNumber,
  active,
  root,
  rootMargin,
  width,
  onJump,
}: {
  doc: PDFDocumentProxy;
  pageNumber: number;
  active: boolean;
  root: HTMLElement | null;
  rootMargin: string;
  width: number;
  onJump: (n: number) => void;
}) {
  const wrapRef = useRef<HTMLButtonElement>(null);
  const canvasRef = useRef<HTMLCanvasElement>(null);
  const taskRef = useRef<RenderTask | null>(null);
  const [near, setNear] = useState(false);

  useEffect(() => {
    const el = wrapRef.current;
    if (!el || root === null) return;
    const observer = new IntersectionObserver(
      (entries) => entries[0] && setNear(entries[0].isIntersecting),
      { root, rootMargin, threshold: 0 },
    );
    observer.observe(el);
    return () => observer.disconnect();
  }, [root, rootMargin]);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas || !near) return;
    let disposed = false;
    void (async () => {
      try {
        const page = await doc.getPage(pageNumber);
        if (disposed) return;
        const base = page.getViewport({ scale: 1 });
        const viewport = page.getViewport({ scale: width / base.width });
        const ctx = canvas.getContext('2d');
        if (!ctx) return;
        canvas.width = Math.floor(viewport.width);
        canvas.height = Math.floor(viewport.height);
        const task = page.render({ canvas, canvasContext: ctx, viewport });
        taskRef.current = task;
        await task.promise;
      } catch {
        /* إلغاء — يُتجاهَل */
      }
    })();
    return () => {
      disposed = true;
      taskRef.current?.cancel();
    };
  }, [near, doc, pageNumber, width]);

  return (
    <button
      ref={wrapRef}
      type="button"
      onClick={() => onJump(pageNumber)}
      aria-label={`صفحة ${pageNumber}`}
      aria-current={active ? 'true' : undefined}
      className={`relative flex shrink-0 flex-col items-center gap-0.5 transition-opacity ${active ? 'opacity-100' : 'opacity-60 hover:opacity-90'}`}
    >
      <span
        className={`block bg-white ${active ? 'outline outline-2 outline-primary' : 'outline outline-1 outline-white/20'}`}
        style={{ width }}
      >
        <canvas ref={canvasRef} className="block size-full" />
      </span>
      <span className="text-[10px] tabular-nums text-neutral-400">{pageNumber}</span>
    </button>
  );
}

// شريط مصغّرات (أفقيّ = أسفل الجوّال / عموديّ = مُصغِّر التنقّل الجانبيّ على سطح المكتب): كلّ
// المصغّرات بتحميل كسول (لا تُرسَم إلا القريبة في الشريط)؛ نقر ⇒ انتقال؛ تتبُّع الصفحة الحاليّة.
export function ReaderThumbnails({
  doc,
  numPages,
  currentPage,
  onJump,
  orientation = 'horizontal',
  thumbWidth = 56,
}: {
  doc: PDFDocumentProxy;
  numPages: number;
  currentPage: number;
  onJump: (n: number) => void;
  orientation?: Orientation;
  thumbWidth?: number;
}) {
  const stripRef = useRef<HTMLDivElement>(null);
  const [root, setRoot] = useState<HTMLElement | null>(null);
  const vertical = orientation === 'vertical';

  useEffect(() => {
    setRoot(stripRef.current);
  }, []);

  useEffect(() => {
    const strip = stripRef.current;
    const el = strip?.querySelector<HTMLElement>('[aria-current="true"]');
    el?.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
  }, [currentPage]);

  return (
    <div
      ref={stripRef}
      dir="rtl"
      role="tablist"
      aria-label="صفحات العدد"
      className={
        vertical
          ? 'flex w-24 shrink-0 flex-col items-center gap-2 overflow-y-auto border-s border-white/10 bg-[#161616] px-2 py-3'
          : 'flex shrink-0 items-end gap-2 overflow-x-auto border-t border-white/10 bg-[#1a1a1a] px-3 py-2'
      }
    >
      {Array.from({ length: numPages }, (_, i) => i + 1).map((n) => (
        <ReaderThumb
          key={n}
          doc={doc}
          pageNumber={n}
          active={n === currentPage}
          root={root}
          rootMargin={vertical ? '300px 0px 300px 0px' : '0px 300px 0px 300px'}
          width={thumbWidth}
          onJump={onJump}
        />
      ))}
    </div>
  );
}
