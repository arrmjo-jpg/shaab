'use client';

import { useCallback, useEffect, useRef } from 'react';

// ذاكرة القراءة — تحفظ آخر صفحة + التكبير + الوضع لكلّ عدد في localStorage فيُستأنف العرض عند
// العودة. مفتاح مستقلّ عن قارئ Blade القديم (`epaper:state:*`) كي لا يتداخلا.
export interface ReadingMemory {
  page: number;
  scale: number;
  rotation: number;
  fitMode: 'width' | 'page' | 'custom';
}

const storageKey = (id: string) => `newspaper:reader:${id}`;

export function useReadingMemory(id: string) {
  const timer = useRef<number>(0);

  const read = useCallback((): ReadingMemory | null => {
    try {
      const raw = localStorage.getItem(storageKey(id));
      if (!raw) return null;
      const parsed: unknown = JSON.parse(raw);
      if (
        parsed &&
        typeof parsed === 'object' &&
        typeof (parsed as ReadingMemory).page === 'number'
      ) {
        return parsed as ReadingMemory;
      }
    } catch {
      /* تخزين معطوب/ممنوع — يُتجاهَل */
    }
    return null;
  }, [id]);

  // كتابة مؤجَّلة (debounce) — لا نُرهق التخزين على كلّ بكسل تمرير/تكبير.
  const save = useCallback(
    (memory: ReadingMemory) => {
      window.clearTimeout(timer.current);
      timer.current = window.setTimeout(() => {
        try {
          localStorage.setItem(storageKey(id), JSON.stringify(memory));
        } catch {
          /* الحصّة ممتلئة/ممنوع — يُتجاهَل */
        }
      }, 400);
    },
    [id],
  );

  useEffect(() => () => window.clearTimeout(timer.current), []);

  return { read, save };
}
