import { http } from './http/client';
import type { ApiSuccess } from '@/types/api';
import type { AiUsageMeta, AiUsagePage, AiUsageQuery, AiUsageRow } from '@/types/ai.types';
import type {
  AiContentAnalysis,
  AiEditorialContext,
  AiExcerptResult,
  AiHeadlineSuggestions,
  AiRewriteMode,
  AiSeoAnalysis,
  AiTagSuggestions,
  ContentLocale,
} from '@/types/content.types';

/**
 * المساعد التحريري — مساعدة (اقتراحات) لا توليد تلقائي. كل نداء يمرّ عبر
 * نقاط النهاية المحمية بـ ai.use + throttle، والمزوّد الفعلي يُختار في الخادم.
 */
export const aiService = {
  async headlines(ctx: AiEditorialContext): Promise<AiHeadlineSuggestions> {
    const { data } = await http.post<ApiSuccess<AiHeadlineSuggestions>>('/admin/ai/headlines', ctx);
    return data.data;
  },

  async excerpt(ctx: AiEditorialContext): Promise<AiExcerptResult> {
    const { data } = await http.post<ApiSuccess<AiExcerptResult>>('/admin/ai/excerpt', ctx);
    return data.data;
  },

  async rewrite(text: string, mode: AiRewriteMode, locale: ContentLocale): Promise<string> {
    const { data } = await http.post<ApiSuccess<{ rewrite: string }>>('/admin/ai/rewrite', {
      text,
      mode,
      locale,
    });
    return data.data.rewrite;
  },

  async tags(ctx: AiEditorialContext): Promise<AiTagSuggestions> {
    const { data } = await http.post<ApiSuccess<AiTagSuggestions>>('/admin/ai/tags', ctx);
    return data.data;
  },

  async analyze(ctx: AiEditorialContext): Promise<AiContentAnalysis> {
    const { data } = await http.post<ApiSuccess<AiContentAnalysis>>('/admin/ai/analyze', ctx);
    return data.data;
  },

  async seo(payload: {
    title?: string;
    excerpt?: string;
    body?: string;
    slug?: string;
    tags?: string[];
    locale?: ContentLocale;
  }): Promise<AiSeoAnalysis> {
    const { data } = await http.post<ApiSuccess<AiSeoAnalysis>>('/admin/ai/seo', payload);
    return data.data;
  },

  /**
   * رؤية استخدام/تكلفة الذكاء الاصطناعي (قراءة فقط) — يتطلّب ai.settings.
   * يُرجع الصفوف المُرشَّحة + الإجماليات/التوزيع/الاتجاه/الحدود (في meta).
   */
  async usage(params: AiUsageQuery = {}): Promise<AiUsagePage> {
    const { data } = await http.get<ApiSuccess<AiUsageRow[]>>('/admin/ai/usage', { params });
    return { rows: data.data, meta: data.meta as unknown as AiUsageMeta };
  },
};
