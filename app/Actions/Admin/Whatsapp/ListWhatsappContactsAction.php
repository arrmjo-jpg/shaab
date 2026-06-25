<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappContactStatus;
use App\Http\Resources\Admin\Whatsapp\WhatsappContactResource;
use App\Models\WhatsappContact;
use App\Support\Responses\ApiResponse;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * قائمة جهات الاتصال — مرقّمة + بحث (الاسم/الهاتف) + فلترة (المجموعة/الحالة) + فرز
 * allow-list. نفس بنية ListContactMessagesAction (pagination من config، meta موحّد).
 */
class ListWhatsappContactsAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = WhatsappContact::query()->with('groups:id,name');

        $status = (string) request()->query('status', '');
        if ($status !== '' && in_array($status, WhatsappContactStatus::values(), true)) {
            $query->where('status', $status);
        }

        $groupId = (int) request()->integer('group_id', 0);
        if ($groupId > 0) {
            $query->whereHas('groups', fn (Builder $q) => $q->where('whatsapp_groups.id', $groupId));
        }

        $search = trim((string) request()->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('phone', 'like', '%'.$search.'%');
            });
        }

        $sortable = ['created_at', 'name'];
        $sort = in_array((string) request()->query('sort', ''), $sortable, true) ? (string) request()->query('sort') : 'created_at';
        $dir = request()->query('dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        $items = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: WhatsappContactResource::collection($items)->resolve(),
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
