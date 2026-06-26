// أنواع رؤية استخدام الذكاء الاصطناعي (AI usage visibility) — لا محتوى حسّاس.
// التوكِنات والتكلفة تقديرية (من حجم الإدخال/الإخراج)، ليست فوترة دقيقة.

export interface AiUsageRow {
  id: number;
  user: { id: number; name: string } | null;
  user_id: number | null;
  provider: string;
  action: string;
  source: string;
  tokens: number;
  estimated_cost: number;
  created_at: string | null;
}

export interface AiUsageTotals {
  requests: number;
  tokens: number;
  estimated_cost: number;
}

export interface AiUsageGroup {
  label: string;
  requests: number;
  tokens: number;
  estimated_cost: number;
}

export interface AiUsageTrendPoint {
  day: string;
  requests: number;
  estimated_cost: number;
}

export interface AiUsageCaps {
  daily_requests: number;
  monthly_requests: number;
  user_daily_requests: number;
  monthly_budget_usd: number;
  remaining: {
    daily_requests: number | null;
    monthly_requests: number | null;
    monthly_budget_usd: number | null;
  };
}

export interface AiUsageMeta {
  pagination: {
    total: number;
    count: number;
    per_page: number;
    current_page: number;
    total_pages: number;
  };
  totals: { today: AiUsageTotals; month: AiUsageTotals };
  by_provider: AiUsageGroup[];
  by_action: AiUsageGroup[];
  trend: AiUsageTrendPoint[];
  caps: AiUsageCaps;
}

export interface AiUsagePage {
  rows: AiUsageRow[];
  meta: AiUsageMeta;
}

export interface AiUsageQuery {
  page?: number;
  per_page?: number;
  'filter[provider]'?: string;
  'filter[action]'?: string;
  'filter[source]'?: string;
  'filter[user_id]'?: number;
  'filter[from]'?: string;
  'filter[to]'?: string;
  sort?: string;
}
