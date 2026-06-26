import 'server-only';
import { cache } from 'react';
import { z } from 'zod';

import { env } from './env';
import { getGovernorate, JORDAN_GOVERNORATES, type Governorate } from './governorates';

// طبقة الطقس (server-only) — تستهلك OpenWeather. المفتاح خادميّ بحت (env.openWeatherKey)؛ العميل يمرّ عبر
// route handler ‎/api/weather‎. **الواجهة:** `data/2.5/weather` (حاليّ) + `data/2.5/forecast` (5 أيّام/3س ⇒
// ساعيّ + يوميّ). One Call 3.0 مدفوع (يُبدَّل `fetchCurrent`/`fetchForecast` فقط لو اشتُرك). أيّ فشل ⇒ null/[] لا تلفيق.

export { JORDAN_GOVERNORATES, type Governorate };

export interface WeatherSnapshot {
  govId: string;
  city: string;
  temp: number;
  feelsLike: number;
  tempMin: number;
  tempMax: number;
  humidity: number;
  windKmh: number;
  pressure: number;
  description: string;
  icon: string;
}

export interface HourlyForecast {
  time: string; // وقت محليّ (عمّان) بالعربيّة
  temp: number;
  icon: string;
}

export interface DailyForecast {
  date: string;
  dayLabel: string;
  tempMin: number;
  tempMax: number;
  icon: string;
  description: string;
}

export interface WeatherFull {
  govId: string;
  city: string;
  current: WeatherSnapshot;
  hourly: HourlyForecast[];
  daily: DailyForecast[];
}

const OwmWeatherSchema = z.object({
  name: z.string().nullish(),
  weather: z.array(z.object({ description: z.string().nullish(), icon: z.string().nullish() })).nullish(),
  main: z.object({
    temp: z.number(),
    feels_like: z.number().nullish(),
    temp_min: z.number().nullish(),
    temp_max: z.number().nullish(),
    humidity: z.number().nullish(),
    pressure: z.number().nullish(),
  }),
  wind: z.object({ speed: z.number().nullish(), deg: z.number().nullish() }).nullish(),
});

const OwmForecastSchema = z.object({
  list: z.array(
    z.object({
      dt: z.number(),
      dt_txt: z.string(),
      main: z.object({
        temp: z.number(),
        temp_min: z.number().nullish(),
        temp_max: z.number().nullish(),
      }),
      weather: z.array(z.object({ icon: z.string().nullish(), description: z.string().nullish() })).nullish(),
    }),
  ),
});

type OwmWeather = z.infer<typeof OwmWeatherSchema>;
type OwmForecast = z.infer<typeof OwmForecastSchema>;

const fetchCurrent = cache(async (lat: number, lon: number): Promise<OwmWeather | null> => {
  if (!env.openWeatherKey) return null;
  try {
    const url =
      `https://api.openweathermap.org/data/2.5/weather` +
      `?lat=${lat}&lon=${lon}&units=metric&lang=ar&appid=${env.openWeatherKey}`;
    const res = await fetch(url, { signal: AbortSignal.timeout(4000), next: { revalidate: 900, tags: ['weather'] } });
    if (!res.ok) return null;
    const parsed = OwmWeatherSchema.safeParse(await res.json());
    return parsed.success ? parsed.data : null;
  } catch {
    return null;
  }
});

const fetchForecast = cache(async (lat: number, lon: number): Promise<OwmForecast | null> => {
  if (!env.openWeatherKey) return null;
  try {
    const url =
      `https://api.openweathermap.org/data/2.5/forecast` +
      `?lat=${lat}&lon=${lon}&units=metric&lang=ar&appid=${env.openWeatherKey}`;
    const res = await fetch(url, { signal: AbortSignal.timeout(4000), next: { revalidate: 1800, tags: ['weather'] } });
    if (!res.ok) return null;
    const parsed = OwmForecastSchema.safeParse(await res.json());
    return parsed.success ? parsed.data : null;
  } catch {
    return null;
  }
});

const AR_WEEKDAY = new Intl.DateTimeFormat('ar', { weekday: 'short' });
const AR_HOUR = new Intl.DateTimeFormat('ar', { hour: 'numeric', hour12: true, timeZone: 'Asia/Amman' });

function weekdayAr(dateYmd: string): string {
  const d = new Date(`${dateYmd}T12:00:00`);
  return Number.isNaN(d.getTime()) ? '' : AR_WEEKDAY.format(d);
}

function toSnapshot(gov: Governorate, raw: OwmWeather): WeatherSnapshot {
  return {
    govId: gov.id,
    city: gov.name,
    temp: Math.round(raw.main.temp),
    feelsLike: Math.round(raw.main.feels_like ?? raw.main.temp),
    tempMin: Math.round(raw.main.temp_min ?? raw.main.temp),
    tempMax: Math.round(raw.main.temp_max ?? raw.main.temp),
    humidity: raw.main.humidity ?? 0,
    windKmh: Math.round((raw.wind?.speed ?? 0) * 3.6),
    pressure: raw.main.pressure ?? 0,
    description: raw.weather?.[0]?.description ?? '',
    icon: raw.weather?.[0]?.icon ?? '01d',
  };
}

// أوّل ~24س (8 خطوات × 3س) ساعيّاً.
function extractHourly(raw: OwmForecast): HourlyForecast[] {
  return raw.list.slice(0, 8).map((e) => ({
    time: AR_HOUR.format(new Date(e.dt * 1000)),
    temp: Math.round(e.main.temp),
    icon: e.weather?.[0]?.icon ?? '01d',
  }));
}

// تجميع توقّع الـ3س إلى أيّام: صغرى/عظمى + أيقونة الظهيرة.
function aggregateDaily(raw: OwmForecast): DailyForecast[] {
  const byDate = new Map<
    string,
    { min: number; max: number; icon: string; description: string; noonDiff: number }
  >();
  for (const e of raw.list) {
    const date = e.dt_txt.slice(0, 10);
    const hour = Number(e.dt_txt.slice(11, 13));
    const cur =
      byDate.get(date) ??
      { min: Number.POSITIVE_INFINITY, max: Number.NEGATIVE_INFINITY, icon: '01d', description: '', noonDiff: 99 };
    cur.min = Math.min(cur.min, e.main.temp_min ?? e.main.temp);
    cur.max = Math.max(cur.max, e.main.temp_max ?? e.main.temp);
    const diff = Math.abs(hour - 12);
    if (diff < cur.noonDiff) {
      cur.noonDiff = diff;
      cur.icon = e.weather?.[0]?.icon ?? '01d';
      cur.description = e.weather?.[0]?.description ?? '';
    }
    byDate.set(date, cur);
  }
  return [...byDate.entries()]
    .map(([date, v]) => ({
      date,
      dayLabel: weekdayAr(date),
      tempMin: Math.round(v.min),
      tempMax: Math.round(v.max),
      icon: v.icon,
      description: v.description,
    }))
    .slice(0, 7);
}

export const getGovernorateWeather = cache(async (govId: string): Promise<WeatherSnapshot | null> => {
  const gov = getGovernorate(govId);
  if (!gov) return null;
  const raw = await fetchCurrent(gov.lat, gov.lon);
  return raw ? toSnapshot(gov, raw) : null;
});

// طقس محافظة كامل (حاليّ + ساعيّ + يوميّ). فشل الحاليّ ⇒ null.
export const getGovernorateWeatherFull = cache(async (govId: string): Promise<WeatherFull | null> => {
  const gov = getGovernorate(govId);
  if (!gov) return null;
  const [rawCurrent, rawForecast] = await Promise.all([
    fetchCurrent(gov.lat, gov.lon),
    fetchForecast(gov.lat, gov.lon),
  ]);
  if (!rawCurrent) return null;
  return {
    govId: gov.id,
    city: gov.name,
    current: toSnapshot(gov, rawCurrent),
    hourly: rawForecast ? extractHourly(rawForecast) : [],
    daily: rawForecast ? aggregateDaily(rawForecast) : [],
  };
});

// كلّ المحافظات (حاليّ فقط) — يتجاهل الفاشل.
export const getAllGovernoratesWeather = cache(async (): Promise<WeatherSnapshot[]> => {
  const results = await Promise.all(
    JORDAN_GOVERNORATES.map(async (g) => {
      const raw = await fetchCurrent(g.lat, g.lon);
      return raw ? toSnapshot(g, raw) : null;
    }),
  );
  return results.filter((x): x is WeatherSnapshot => x !== null);
});

// كلّ المحافظات **كاملةً** (حاليّ + توقّع أسبوعيّ) — لشبكة /weather. يتجاهل الفاشل. كلّها فشلت ⇒ [].
export const getAllGovernoratesWeatherFull = cache(async (): Promise<WeatherFull[]> => {
  const results = await Promise.all(JORDAN_GOVERNORATES.map((g) => getGovernorateWeatherFull(g.id)));
  return results.filter((x): x is WeatherFull => x !== null);
});
