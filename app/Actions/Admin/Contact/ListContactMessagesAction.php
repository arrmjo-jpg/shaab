<?php

declare(strict_types=1);

namespace App\Actions\Admin\Contact;

use App\Enums\ContactMessageStatus;
use App\Enums\ContactMessageType;
use App\Http\Resources\Admin\Contact\ContactMessageResource;
use App\Models\ContactMessage;
use App\Support\Responses\ApiResponse;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

/**
 * قائمة رسائل الاتصال (إدارة) — مرقّمة + فلاتر (status/type) + بحث (الاسم/البريد/الموضوع/المتن)
 * + فرز allow-list. نفس بنية ListCommentsAction (pagination من config، meta موحّد).
 */
class ListContactMessagesAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = ContactMessage::query()->with('repliedBy:id,name');

        $status = (string) request()->query('status', '');
        if ($status !== '' && in_array($status, ContactMessageStatus::values(), true)) {
            $query->where('status', $status);
        }

        $type = (string) request()->query('type', '');
        if ($type !== '' && in_array($type, ContactMessageType::values(), true)) {
            $query->where('type', $type);
        }

        $search = trim((string) request()->query('q', ''));
        if ($search !== '') {
            $query->where(function (Builder $q) use ($search): void {
                $q->where('name', 'like', '%'.$search.'%')
                    ->orWhere('email', 'like', '%'.$search.'%')
                    ->orWhere('subject', 'like', '%'.$search.'%')
                    ->orWhere('message', 'like', '%'.$search.'%');
            });
        }

        $sortable = ['created_at', 'status', 'type'];
        $sort = in_array((string) request()->query('sort', ''), $sortable, true) ? (string) request()->query('sort') : 'created_at';
        $dir = request()->query('dir') === 'asc' ? 'asc' : 'desc';
        $query->orderBy($sort, $dir);

        $items = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: ContactMessageResource::collection($items)->resolve(),
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
