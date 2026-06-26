import { useMutation } from '@tanstack/react-query';
import { aiService } from '@/services/ai.service';
import { useToast } from '@/hooks/useToast';
import type { NormalizedError } from '@/types/api';
import type {
  AiEditorialContext,
  AiRewriteMode,
  ContentLocale,
} from '@/types/content.types';

/**
 * مساعدات الـ Copilot — كلها mutations (نداء عند الطلب فقط، لا تلقائي).
 * أعطال المزوّد (503) تُعرض كـ toast لطيف دون تجميد الواجهة.
 */

export function useAiHeadlines() {
  const { error } = useToast();
  return useMutation({
    mutationFn: (ctx: AiEditorialContext) => aiService.headlines(ctx),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useAiExcerpt() {
  const { error } = useToast();
  return useMutation({
    mutationFn: (ctx: AiEditorialContext) => aiService.excerpt(ctx),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useAiRewrite() {
  const { error } = useToast();
  return useMutation({
    mutationFn: (v: { text: string; mode: AiRewriteMode; locale: ContentLocale }) =>
      aiService.rewrite(v.text, v.mode, v.locale),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useAiTags() {
  const { error } = useToast();
  return useMutation({
    mutationFn: (ctx: AiEditorialContext) => aiService.tags(ctx),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useAiAnalyze() {
  const { error } = useToast();
  return useMutation({
    mutationFn: (ctx: AiEditorialContext) => aiService.analyze(ctx),
    onError: (e: NormalizedError) => error(e.message),
  });
}

export function useAiSeo() {
  const { error } = useToast();
  return useMutation({
    mutationFn: (payload: {
      title?: string;
      excerpt?: string;
      body?: string;
      slug?: string;
      tags?: string[];
      locale?: ContentLocale;
    }) => aiService.seo(payload),
    onError: (e: NormalizedError) => error(e.message),
  });
}
