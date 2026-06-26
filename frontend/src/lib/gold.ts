import 'server-only';
import { cache } from 'react';
import { z } from 'zod';

// مصدر الأسعار الحيّ (royanews). الواجهة **متقلّبة** (502 متقطّع) ⇒ أيّ فشل ⇒ null (حالة فارغة أنيقة، لا تلفيق).
const GOLD_API = 'https://api.royanews.tv/api/v2/gold-prices';

const Cell = z
  .object({
    price: z.string().nullish(),
    increase: z.boolean().nullish(),
    percent_change: z.number().nullish(),
  })
  .nullish();

const Record = z
  .object({
    id: z.number().nullish(),
    updated: z.boolean().nullish(),
    sell_24k: Cell,
    buy_24k: Cell,
    sell_21k: Cell,
    buy_21k: Cell,
    sell_18k: Cell,
    buy_18k: Cell,
    sell_14k: Cell,
    buy_14k: Cell,
    sell_lira_english: Cell,
    buy_lira_english: Cell,
    sell_lira_rshadi: Cell,
    buy_lira_rshadi: Cell,
    updated_at: z.string().nullish(),
    shareableLink: z.string().nullish(),
  })
  .passthrough();

const Envelope = z
  .object({ data: z.object({ gold_prices: z.array(Record).nullish() }).passthrough().nullish() })
  .passthrough();

type Raw = z.infer<typeof Record>;
type RawCell = z.infer<typeof Cell>;

// صفّ عيار/ليرة جاهز للعرض — بيع/شراء + اتّجاه + نسبة. القيم الصفر (عيار 14 غير متوفّر) تُحذَف.
export interface GoldRow {
  key: string;
  label: string;
  sell: number;
  buy: number;
  up: boolean;
  pct: number;
}

export interface GoldPrices {
  karats: GoldRow[]; // 24 / 21 / 18 (و14 إن توفّر)
  liras: GoldRow[]; // ليرة إنجليزي / ليرة رشادي
  updatedRelative: string | null;
  shareLink: string | null;
}

function num(s: string | null | undefined): number {
  const n = Number.parseFloat(String(s ?? '').replace(/[^\d.]/g, ''));
  return Number.isFinite(n) ? n : 0;
}

function row(key: string, label: string, sell: RawCell, buy: RawCell): GoldRow | null {
  const s = num(sell?.price);
  const b = num(buy?.price);
  if (s <= 0 && b <= 0) return null; // غير متوفّر ⇒ يُخفى (لا قيمة صفريّة معروضة)
  return { key, label, sell: s, buy: b, up: !!sell?.increase, pct: Math.abs(sell?.percent_change ?? 0) };
}

function mapRecord(r: Raw): GoldPrices {
  const karats = [
    row('24', 'عيار 24', r.sell_24k, r.buy_24k),
    row('21', 'عيار 21', r.sell_21k, r.buy_21k),
    row('18', 'عيار 18', r.sell_18k, r.buy_18k),
    row('14', 'عيار 14', r.sell_14k, r.buy_14k),
  ].filter((x): x is GoldRow => x !== null);

  const liras = [
    row('lira_en', 'ليرة إنجليزي', r.sell_lira_english, r.buy_lira_english),
    row('lira_rs', 'ليرة رشادي', r.sell_lira_rshadi, r.buy_lira_rshadi),
  ].filter((x): x is GoldRow => x !== null);

  return { karats, liras, updatedRelative: r.updated_at ?? null, shareLink: r.shareableLink ?? null };
}

interface FetchOpts {
  attempts?: number; // عدد المحاولات (الواجهة متقلّبة: 502/مهلة متقطّعة لنفس الـURL)
  timeoutMs?: number; // مهلة كلّ محاولة
  store?: boolean; // true = ISR (revalidate)؛ false = no-store (كيلا يَعلَق فشل 502 في الكاش)
}

async function fetchGold(qs: string, opts: FetchOpts = {}): Promise<GoldPrices | null> {
  const { attempts = 1, timeoutMs = 4500, store = true } = opts;

  for (let i = 0; i < attempts; i++) {
    try {
      const res = await fetch(`${GOLD_API}${qs}`, {
        headers: { 'User-Agent': 'Mozilla/5.0', Accept: 'application/json' },
        signal: AbortSignal.timeout(timeoutMs),
        // الهوم: ISR (جلب كل 300s، حارس أداء). الأرشيف: no-store كيلا يَعلَق فشلٌ متقطّع للتاريخ.
        ...(store ? { next: { revalidate: 300, tags: ['gold'] } } : { cache: 'no-store' as const }),
      });
      if (res.ok) {
        const parsed = Envelope.safeParse(await res.json());
        const rec = parsed.success ? parsed.data.data?.gold_prices?.[0] : null;
        return rec ? mapRecord(rec) : null; // نجاح (ولو بلا سجلّ) ⇒ لا تكرار
      }
      // 502/خطأ خادم ⇒ أعد المحاولة (تقلّب معروف للواجهة)
    } catch {
      // مهلة/شبكة ⇒ أعد المحاولة إن بقيت محاولات
    }
  }
  return null; // فشل كلّ المحاولات ⇒ حالة فارغة أنيقة (لا تلفيق)
}

// أحدث أسعار الذهب محليّاً — محاولة واحدة + ISR (حارس أداء الهوم).
export const getLatestGold = cache((): Promise<GoldPrices | null> => fetchGold('?type=1'));

// أسعار الذهب لتاريخ محدّد (للأرشيف/المقارنة) — yyyy-mm-dd. الواجهة متقلّبة جدّاً للتواريخ
// (502/مهلة متقطّعة رغم توفّر البيانات) ⇒ 3 محاولات + مهلة أطول + بلا تخزين ⇒ موثوقيّة المقارنة.
export const getGoldForDate = cache((date: string): Promise<GoldPrices | null> =>
  fetchGold(`?type=1&date_from=${encodeURIComponent(date)}&date_to=${encodeURIComponent(date)}`, {
    attempts: 3,
    timeoutMs: 6000,
    store: false,
  }),
);
