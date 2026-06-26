import { useQuery } from '@tanstack/react-query';
import { aiService } from '@/services/ai.service';
import type { AiUsageQuery } from '@/types/ai.types';

const USAGE_KEY = ['ai', 'usage'] as const;

export function useAiUsage(params: AiUsageQuery) {
  return useQuery({
    queryKey: [...USAGE_KEY, params],
    queryFn: () => aiService.usage(params),
    placeholderData: (prev) => prev, // إبقاء البيانات السابقة أثناء تغيّر المرشّحات
  });
}
