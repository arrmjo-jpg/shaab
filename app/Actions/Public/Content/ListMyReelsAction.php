<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Http\Resources\Admin\Content\ReelResource;
use App\Models\Reel;
use App\Models\User;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * قائمة ريلز الكاتب نفسه (نطاق عام — V1).
 *
 * تُرجع كل ريلز الكاتب بكل الحالات (draft/submitted/.../rejected) ليرى حالة
 * ما أرسله. غير قابلة للكاش (per-user، تحوي مسودّات خاصّة). القراءة محصورة
 * بـ author_id = الفاعل — لا يرى محتوى غيره. يعيد استخدام ReelResource
 * (نفس عقد الإنشاء/الإرسال في V1) للاتساق وإظهار status.
 */
class ListMyReelsAction
{
    public function handle(User $actor, Request $request): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) $request->integer('per_page', $default), $max));

        $paginator = QueryBuilder::for(
            Reel::query()
                ->where('author_id', $actor->id)
                ->with(['mediaAsset'])
        )
            ->allowedFilters(
                AllowedFilter::exact('status'),
            )
            ->allowedSorts('created_at', 'updated_at')
            ->defaultSort('-created_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(
            data: ReelResource::collection($paginator)->resolve(),
            meta: ['pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
            ]],
        );
    }
}
