<?php

declare(strict_types=1);

namespace App\Actions\Admin\Media;

use App\Http\Resources\Admin\Media\MediaAssetResource;
use App\Models\MediaAsset;
use App\Support\Media\MediaUsage;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قائمة أصول المكتبة المركزية (استوديو الوسائط + صفحة حوكمة المكتبة).
 * يقتصر على أصول المكتبة (assets/ + الفيديو الخارجي) — لا أصول الإعدادات.
 *
 * فلاتر: type (image|video|external) + provider + بحث متعدّد الحقول.
 * يرفق عدّادات الاستخدام (usage_count) عبر withCount. مرقّمة، أحدث أولاً.
 */
class ListMediaAssetsAction
{
    public function handle(array $filters): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) ($filters['per_page'] ?? $default), $max));

        $query = MediaAsset::query()
            ->library()
            ->withCount(MediaUsage::countSelectors());

        // النوع: صورة | فيديو مرفوع | فيديو خارجي
        match ($filters['type'] ?? '') {
            'image' => $query->where('mime_type', 'like', 'image/%'),
            'video' => $query->where('kind', 'file')->where('mime_type', 'like', 'video/%'),
            'external' => $query->where('kind', 'external'),
            default => null,
        };

        // تشخيص المشغّل: حالة المعالجة (مثلاً failed لعرض ما تعثّر).
        $processingStatus = trim((string) ($filters['processing_status'] ?? ''));
        if ($processingStatus !== '') {
            $query->where('processing_status', $processingStatus);
        }

        // مزوّد الفيديو الخارجي
        $provider = trim((string) ($filters['provider'] ?? ''));
        if ($provider !== '') {
            $query->where('provider', $provider);
        }

        // بحث متعدّد الحقول (اسم/alt/caption/credit/source/نوع MIME)
        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $like = '%'.$search.'%';
            $query->where(function ($q) use ($like): void {
                $q->where('original_name', 'like', $like)
                    ->orWhere('filename', 'like', $like)
                    ->orWhere('alt', 'like', $like)
                    ->orWhere('caption', 'like', $like)
                    ->orWhere('credit', 'like', $like)
                    ->orWhere('source', 'like', $like)
                    ->orWhere('mime_type', 'like', $like);
            });
        }

        $assets = $query->latest('id')->paginate($perPage);

        return ApiResponse::success(
            data: MediaAssetResource::collection($assets)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $assets->total(),
                    'count' => $assets->count(),
                    'per_page' => $assets->perPage(),
                    'current_page' => $assets->currentPage(),
                    'total_pages' => $assets->lastPage(),
                ],
            ]
        );
    }
}
