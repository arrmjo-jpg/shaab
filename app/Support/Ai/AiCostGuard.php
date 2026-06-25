<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Models\AiUsage;
use Illuminate\Support\Facades\Auth;

/**
 * حارس تكلفة/استخدام الذكاء الاصطناعي — يفرض الحدود الإنتاجية قبل أي نداء فعلي.
 * يقرأ الحدود من config('ai.caps') (تُضبط بيئياً، 0 = بلا حدّ) ويقارنها بعدّادات
 * الاستخدام الفعلي (المُثبَّت في ai_usages للنداءات الحقيقية فقط: source=ai).
 *
 * مساعد ساكن لا حالة له. exceeded() تُعيد رمز السبب عند التجاوز، أو null إن سُمح.
 * عند التجاوز: الميزات الهجينة تسقط إلى بدائل حتمية، والميزات التي تتطلّب ذكاءً
 * ترفض بلطف (429). فاشل-آمن: لا يمنع إن لم تُضبط حدود.
 */
final class AiCostGuard
{
    /**
     * @return string|null رمز السبب (daily_requests|monthly_requests|user_daily_requests|monthly_budget) أو null
     */
    public static function exceeded(): ?string
    {
        $caps = (array) config('ai.caps', []);

        $daily = (int) ($caps['daily_requests'] ?? 0);
        if ($daily > 0 && self::aiRequests(now()->startOfDay()) >= $daily) {
            return 'daily_requests';
        }

        $monthly = (int) ($caps['monthly_requests'] ?? 0);
        if ($monthly > 0 && self::aiRequests(now()->startOfMonth()) >= $monthly) {
            return 'monthly_requests';
        }

        $userDaily = (int) ($caps['user_daily_requests'] ?? 0);
        $userId = Auth::id();
        if ($userDaily > 0 && $userId !== null
            && self::aiRequests(now()->startOfDay(), $userId) >= $userDaily) {
            return 'user_daily_requests';
        }

        $budget = (float) ($caps['monthly_budget_usd'] ?? 0);
        if ($budget > 0 && self::monthlyCost() >= $budget) {
            return 'monthly_budget';
        }

        return null;
    }

    /** عدد النداءات الفعلية (source=ai) منذ لحظة معيّنة، اختيارياً لمستخدم بعينه. */
    private static function aiRequests(\DateTimeInterface $since, ?int $userId = null): int
    {
        return AiUsage::query()
            ->where('source', 'ai')
            ->where('created_at', '>=', $since)
            ->when($userId !== null, fn ($q) => $q->where('user_id', $userId))
            ->count();
    }

    /** مجموع التكلفة المقدّرة للنداءات الفعلية منذ بداية الشهر (USD). */
    private static function monthlyCost(): float
    {
        return (float) AiUsage::query()
            ->where('source', 'ai')
            ->where('created_at', '>=', now()->startOfMonth())
            ->sum('estimated_cost');
    }
}
