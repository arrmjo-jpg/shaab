<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\AiUsage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * تسجيل استخدام المساعد التحريري — آمن وخفيف. يدوّن من فعل ماذا بأي مزوّد ومتى،
 * دون أي محتوى حسّاس (لا نصوص، لا تلقينات، لا مخرجات). للمراقبة والتدقيق وفرض
 * الحدود (راجع AiCostGuard) ولوحة الرصد (/admin/ai/usage).
 *
 * مساعد ساكن لا حالة له — يُستدعى مباشرةً من المتحكّم بلا طبقة خدمة استباقية.
 * يكتب سطر سجلّ (مراقبة فورية) ويُثبّت صفّاً قابلاً للاستعلام (تحليل/حدود).
 */
final class AiUsageLog
{
    /** تقدير: ~4 أحرف لكل توكِن (عقد المزوّد يُعيد نصّاً فقط، بلا عدّ توكِنات فعلي). */
    private const CHARS_PER_TOKEN = 4;

    /**
     * @param  string  $action  العملية (headlines|excerpt|rewrite|tags|seo|analyze)
     * @param  string  $source  مصدر الناتج (ai|auto)
     * @param  string  $provider  معرّف المزوّد القصير (openai|gemini|none)
     * @param  int  $inputChars  حجم الإدخال بالأحرف (لتقدير التوكِنات/التكلفة)
     * @param  int  $outputChars  حجم الإخراج بالأحرف (لتقدير التوكِنات/التكلفة)
     */
    public static function record(
        string $action,
        string $source,
        string $provider,
        int $inputChars = 0,
        int $outputChars = 0,
    ): void {
        $tokens = self::estimateTokens($inputChars + $outputChars);
        $cost = self::estimateCost($provider, $tokens);
        $userId = Auth::id();

        // سطر سجلّ خفيف للمراقبة الفورية (يبقى كما كان).
        Log::channel(config('logging.default'))->info('ai.usage', [
            'action' => $action,
            'source' => $source,
            'provider' => $provider,
            'user_id' => $userId,
            'tokens' => $tokens,
            'estimated_cost' => $cost,
            'at' => now()->toIso8601String(),
        ]);

        // صفّ قابل للاستعلام (تحليل التكلفة + فرض الحدود + لوحة الرصد).
        // فشل الكتابة لا يجوز أن يُسقط طلب المستخدم — التسجيل ثانوي.
        try {
            AiUsage::create([
                'user_id' => $userId,
                'provider' => $provider,
                'action' => $action,
                'source' => $source,
                'tokens' => $tokens,
                'estimated_cost' => $cost,
            ]);
        } catch (\Throwable $e) {
            Log::warning('ai.usage persist failure', ['error' => $e->getMessage()]);
        }
    }

    private static function estimateTokens(int $chars): int
    {
        return $chars > 0 ? (int) ceil($chars / self::CHARS_PER_TOKEN) : 0;
    }

    private static function estimateCost(string $provider, int $tokens): float
    {
        $rate = (float) (config('ai.cost_per_1k_tokens')[$provider] ?? 0);

        return $rate > 0 && $tokens > 0 ? round($tokens / 1000 * $rate, 6) : 0.0;
    }
}
