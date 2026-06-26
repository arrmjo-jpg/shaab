import 'server-only';
import { cache } from 'react';

// بورصة عمّان (ASE) — قائمة الشركات المدرجة (بيانات حقيقيّة). الواجهة تُرجع أسماءً فقط:
// [{ symbol:"", name_long:"<اسم عربيّ>", value:"", market_id:"" }] (symbol/value/market_id فارغة دائماً).
// تُستخدم لعرض «شركات السوق» في التيكر — **لا أسعار/مؤشّر مُختلَق** (الواجهة لا توفّرها أصلاً).
const ASE_API = 'https://www.ase.com.jo/ar/companies/autocomplete?_format=json';

export const getAseCompanies = cache(async (limit = 16): Promise<string[]> => {
  try {
    const res = await fetch(ASE_API, {
      headers: { 'User-Agent': 'Mozilla/5.0', Accept: 'application/json' },
      signal: AbortSignal.timeout(4500), // الموقع الحكوميّ قد يكون بطيئاً — لا نُعلّق الـSSR
      next: { revalidate: 300, tags: ['ase'] },
    });
    if (!res.ok) return [];
    const json: unknown = await res.json();
    if (!Array.isArray(json)) return [];
    const seen = new Set<string>();
    const names: string[] = [];
    for (const item of json) {
      const name = String((item as { name_long?: unknown })?.name_long ?? '').trim();
      if (name && !seen.has(name)) {
        seen.add(name);
        names.push(name);
        if (names.length >= limit) break;
      }
    }
    return names;
  } catch {
    return [];
  }
});
