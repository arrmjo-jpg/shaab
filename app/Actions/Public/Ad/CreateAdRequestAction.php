<?php

declare(strict_types=1);

namespace App\Actions\Public\Ad;

use App\Enums\AdRequestStatus;
use App\Models\AdRequest;
use App\Support\Notifications\AdminAdRequestNotifier;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * إنشاء طلب إعلان من معلِن عامّ. status=new (يقود Badge). meta يلتقط ip/ua. إشعار الإدارة
 * post-commit (best-effort) عبر نظام الإشعارات الحاليّ.
 *
 * المرفق (صورة/ZIP) يُخزَّن على القرص الخاصّ (local، غير منشور) كمرفق خامّ عبر Laravel Storage
 * — لا MediaAsset (مربوط بمستخدم + يرفض ZIP)، ولا فكّ ضغط/تحليل/تنفيذ. يُخدَم للإدارة تنزيلًا فقط.
 */
class CreateAdRequestAction
{
    /** @param  array<string,mixed>  $data */
    public function handle(array $data, Request $request): JsonResponse
    {
        $attachmentPath = null;
        $attachmentName = null;
        $attachmentMime = null;

        $file = $request->file('attachment');
        if ($file !== null) {
            $attachmentPath = $file->store('ad-request-attachments', 'local'); // قرص خاصّ
            $attachmentName = $file->getClientOriginalName();
            $attachmentMime = $file->getClientMimeType();
        }

        $adRequest = AdRequest::create([
            'company_name' => $data['company_name'],
            'contact_name' => $data['contact_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'website' => $data['website'] ?? null,
            'ad_type' => $data['ad_type'],
            'description' => $data['description'],
            'attachment_path' => $attachmentPath,
            'attachment_name' => $attachmentName,
            'attachment_mime' => $attachmentMime,
            'status' => AdRequestStatus::New->value,
            'meta' => [
                'ip' => $request->ip(),
                'ua' => mb_substr((string) $request->userAgent(), 0, 500),
            ],
        ]);

        AdminAdRequestNotifier::created($adRequest);

        return ApiResponse::success(message: __('ad_request.created'), status: 201);
    }
}
