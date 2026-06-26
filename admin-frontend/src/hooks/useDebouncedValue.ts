import { useEffect, useState } from 'react';

/**
 * يُرجع نسخة مؤجّلة من قيمة متغيّرة. يفيد لتقليل ضغط البحث/الفلترة على الـ API
 * عند الكتابة السريعة. الافتراضي 300ms — يكفي للإحساس بالاستجابة دون قذف
 * طلب مع كلّ ضربة مفتاح.
 */
export function useDebouncedValue<T>(value: T, delayMs = 300): T {
  const [debounced, setDebounced] = useState(value);

  useEffect(() => {
    const handle = setTimeout(() => setDebounced(value), delayMs);
    return () => clearTimeout(handle);
  }, [value, delayMs]);

  return debounced;
}
