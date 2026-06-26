// محافظات الأردن الـ12 + الإحداثيّات — **آمن للعميل** (لا أسرار، لا server-only): يستورده العميل
// (weather-card: أقرب محافظة للموقع + التنقّل) والخادم (weather.ts). لا يُعَدّ سرّاً (مجرّد إحداثيّات مدن).
export interface Governorate {
  id: string;
  name: string;
  lat: number;
  lon: number;
}

export const JORDAN_GOVERNORATES: Governorate[] = [
  { id: 'amman', name: 'عمّان', lat: 31.9539, lon: 35.9106 },
  { id: 'irbid', name: 'إربد', lat: 32.5556, lon: 35.85 },
  { id: 'zarqa', name: 'الزرقاء', lat: 32.0728, lon: 36.0876 },
  { id: 'balqa', name: 'البلقاء', lat: 32.0392, lon: 35.7272 },
  { id: 'madaba', name: 'مأدبا', lat: 31.7197, lon: 35.7956 },
  { id: 'karak', name: 'الكرك', lat: 31.1854, lon: 35.7047 },
  { id: 'tafilah', name: 'الطفيلة', lat: 30.8377, lon: 35.6044 },
  { id: 'maan', name: 'معان', lat: 30.1962, lon: 35.7341 },
  { id: 'aqaba', name: 'العقبة', lat: 29.532, lon: 35.0061 },
  { id: 'jerash', name: 'جرش', lat: 32.2747, lon: 35.8956 },
  { id: 'ajloun', name: 'عجلون', lat: 32.3326, lon: 35.7517 },
  { id: 'mafraq', name: 'المفرق', lat: 32.3417, lon: 36.2081 },
];

export function getGovernorate(id: string): Governorate | undefined {
  return JORDAN_GOVERNORATES.find((g) => g.id === id);
}

// أقرب محافظة لإحداثيّة (للموقع الجغرافيّ) — مسافة إقليديّة مربّعة (تكفي على مساحة الأردن الصغيرة).
export function nearestGovernorate(lat: number, lon: number): Governorate {
  let best = JORDAN_GOVERNORATES[0];
  let bestDist = Number.POSITIVE_INFINITY;
  for (const g of JORDAN_GOVERNORATES) {
    const d = (g.lat - lat) ** 2 + (g.lon - lon) ** 2;
    if (d < bestDist) {
      bestDist = d;
      best = g;
    }
  }
  return best;
}
