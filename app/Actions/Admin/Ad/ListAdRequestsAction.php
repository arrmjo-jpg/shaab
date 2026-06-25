<?php

declare(strict_types=1);

namespace App\Actions\Admin\Ad;

use App\Enums\AdRequestStatus;
use App\Http\Resources\Admin\Ad\AdRequestResource;
use App\Models\AdRequest;
use App\Support\Responses\ApiResponse;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * قائمة طلبات الإعلان (إدارة) — مرقّمة + فلتر بالحالة + بحث (الشركة/الشخص/البريد/الوصف) + فرز.
 * نفس بنية ListContactMessagesAction (pagination من config، meta موحّد).
 */
class ListAdRequestsAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = AdRequest::query()->with('reviewedBy:id,name');

        $status = (string) request()->query('status', '');
        if ($status !== '' && in_array($status, AdRequestStatus::values(), true)) {
            $query->where('status', $status);
        }

        $search = trim((string) request()->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('company_name', 'like', '%'.$search.'%')
                    ->orWhere('contact_name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        $sortable = ['created_at', 'status'];
        $sort = in_array((string) request()->query('sort', ''), $sortable, true) ? (string) request()->query('sort') : 'created_at';
        $dir = request()->query('dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        $items = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: AdRequestResource::collection($items)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $items->total(),
                    'count' => $items->count(),
                    'per_page' => $items->perPage(),
                    'current_page' => $items->currentPage(),
                    'total_pages' => $items->lastPage(),
                ],
            ],
        );
    }
}
