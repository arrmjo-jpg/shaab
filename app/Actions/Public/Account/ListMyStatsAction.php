<?php

declare(strict_types=1);

namespace App\Actions\Public\Account;

use App\Enums\EngagementType;
use App\Models\Article;
use App\Models\Reel;
use App\Models\User;
use App\Models\Video;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * إحصاءات لوحة المستخدم (قراءة-فقط) — تجميع per-user عبر محتواه القائم دون أي domain جديد:
 *  • جرد المحتوى (مقالات/أخبار/ريلز/فيديو) حسب author_id.
 *  • سير العمل (منشور/قيد المراجعة/مرفوض/مسودات) عبر status.
 *  • التفاعل (تعليقاته، مفضّلاته، مشاهدات محتواه).
 * مُكاش per-user (CacheKeys::accountStats + CacheTtl::SHORT) — لا مفاتيح يدوية.
 */
class ListMyStatsAction
{
    public function handle(User $user): JsonResponse
    {
        $data = Cache::remember(
            CacheKeys::accountStats($user->id),
            CacheTtl::SHORT,
            fn (): array => $this->compute($user->id),
        );

        return ApiResponse::success(data: $data);
    }

    /** @return array<string,mixed> */
    private function compute(int $userId): array
    {
        return [
            'content' => [
                'articles' => $this->ownedCount('articles', $userId),
                'news' => DB::table('articles')
                    ->where('author_id', $userId)
                    ->whereNull('deleted_at')
                    ->where('type', 'news')
                    ->count(),
                'reels' => $this->ownedCount('reels', $userId),
                'videos' => $this->ownedCount('videos', $userId),
            ],
            'workflow' => $this->workflow($userId),
            'engagement' => [
                'comments' => DB::table('comments')
                    ->where('user_id', $userId)
                    ->whereNull('deleted_at')
                    ->count(),
                'favorites' => DB::table('engagements')
                    ->where('user_id', $userId)
                    ->where('type', EngagementType::Favorite->value)
                    ->count(),
                'views' => $this->views($userId),
            ],
        ];
    }

    private function ownedCount(string $table, int $userId): int
    {
        return DB::table($table)
            ->where('author_id', $userId)
            ->whereNull('deleted_at')
            ->count();
    }

    /**
     * عدّ سير العمل عبر محتوى المستخدم الثلاثة (status نصّيّ). "قيد المراجعة" = submitted + in_review.
     *
     * @return array{published:int,in_review:int,rejected:int,draft:int}
     */
    private function workflow(int $userId): array
    {
        $totals = ['published' => 0, 'in_review' => 0, 'rejected' => 0, 'draft' => 0];

        foreach (['articles', 'reels', 'videos'] as $table) {
            $byStatus = DB::table($table)
                ->where('author_id', $userId)
                ->whereNull('deleted_at')
                ->selectRaw('status, COUNT(*) c')
                ->groupBy('status')
                ->pluck('c', 'status');

            $totals['published'] += (int) ($byStatus['published'] ?? 0);
            $totals['rejected'] += (int) ($byStatus['rejected'] ?? 0);
            $totals['draft'] += (int) ($byStatus['draft'] ?? 0);
            $totals['in_review'] += (int) ($byStatus['submitted'] ?? 0) + (int) ($byStatus['in_review'] ?? 0);
        }

        return $totals;
    }

    /** إجمالي مشاهدات محتوى المستخدم (engagement_counters مربوطاً بالمحتوى حسب author_id). */
    private function views(int $userId): int
    {
        $morphs = [
            'articles' => (new Article)->getMorphClass(),
            'reels' => (new Reel)->getMorphClass(),
            'videos' => (new Video)->getMorphClass(),
        ];

        $total = 0;
        foreach ($morphs as $table => $morph) {
            $total += (int) DB::table('engagement_counters as ec')
                ->join("{$table} as c", 'c.id', '=', 'ec.engageable_id')
                ->where('ec.engageable_type', $morph)
                ->where('c.author_id', $userId)
                ->whereNull('c.deleted_at')
                ->sum('ec.views');
        }

        return $total;
    }
}
