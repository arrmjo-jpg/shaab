<?php

declare(strict_types=1);

namespace App\Actions\Admin\Whatsapp;

use App\Enums\WhatsappCampaignStatus;
use App\Enums\WhatsappCampaignType;
use App\Http\Resources\Admin\Whatsapp\WhatsappCampaignResource;
use App\Models\WhatsappCampaign;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/** سجلّ الحملات — مرقّم + فلترة (نوع/حالة). نفس بنية ListContactMessagesAction. */
class ListWhatsappCampaignsAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = WhatsappCampaign::query()->with('groups:id,name');

        $type = (string) request()->query('type', '');
        if ($type !== '' && in_array($type, WhatsappCampaignType::values(), true)) {
            $query->where('type', $type);
        }

        $status = (string) request()->query('status', '');
        if ($status !== '' && in_array($status, WhatsappCampaignStatus::values(), true)) {
            $query->where('status', $status);
        }

        $items = $query->orderByDesc('created_at')->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: WhatsappCampaignResource::collection($items)->resolve(),
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
