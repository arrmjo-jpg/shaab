<?php

declare(strict_types=1);

namespace App\Actions\Public\Broadcast;

use App\Enums\BroadcastKind;
use App\Http\Resources\Public\Broadcast\PublicBroadcastResource;
use App\Models\Broadcast;
use App\Support\Cache\BroadcastCacheTags;
use App\Support\Cache\CachedRead;
use App\Support\Cache\CacheKeys;
use App\Support\Cache\CacheTtl;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * تفاصيل بثّ عام بالـ (kind + slug). publiclyVisible = عام + ليس مسودّة/مؤرشفاً، مع
 * مطابقة النوع (يمنع ظهور بثّ live تحت /tv أو العكس → 404). كاش single-flight +
 * REALTIME TTL مع إبطال فوري عند التحوّلات/الكتابة (detailTags(slug)). ربط VOD يُحمَّل
 * مُقيَّداً بالعمومية فلا يُسرَّب فيديو خاصّ. لا احتساب مشاهدة هنا (التفاعل في B7).
 */
class ShowPublicBroadcastAction
{
    public function handle(string $kind, string $slug): JsonResponse
    {
        if (BroadcastKind::tryFrom($kind) === null) {
            return ApiResponse::error(__('broadcast.not_found'), [], 404);
        }

        $payload = CachedRead::remember(
            BroadcastCacheTags::detailTags($slug),
            CacheKeys::publicBroadcastDetail($kind, $slug),
            CacheTtl::REALTIME,
            function () use ($kind, $slug): ?array {
                $broadcast = Broadcast::query()
                    ->publiclyVisible()
                    ->ofKind($kind)
                    ->where('slug', $slug)
                    ->with([
                        'category',
                        'engagementCounter',
                        'cover',
                        // ربط VOD يظهر فقط إن كان الفيديو عامّاً قابلاً للتشغيل.
                        'vodVideo' => fn ($q) => $q->public()->playable(),
                    ])
                    ->first();

                return $broadcast === null ? null : (new PublicBroadcastResource($broadcast))->resolve();
            }
        );

        if ($payload === null) {
            return ApiResponse::error(__('broadcast.not_found'), [], 404);
        }

        return ApiResponse::success(data: $payload);
    }
}
