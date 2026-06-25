<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappMessageStatus;
use App\Http\Resources\Admin\Whatsapp\WhatsappCampaignMessageResource;
use App\Models\WhatsappCampaign;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/** رسائل حملة (السجلّ التفصيليّ) — مرقّم + فلترة بالحالة (failed لعرض أسباب الفشل). */
class ListWhatsappCampaignMessagesAction
{
    public function handle(WhatsappCampaign $campaign): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = $campaign->messages()->getQuery();

        $status = (string) request()->query('status', '');
        if ($status !== '' && in_array($status, WhatsappMessageStatus::values(), true)) {
            $query->where('status', $status);
        }

        $items = $query->orderBy('id')->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: WhatsappCampaignMessageResource::collection($items)->resolve(),
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
