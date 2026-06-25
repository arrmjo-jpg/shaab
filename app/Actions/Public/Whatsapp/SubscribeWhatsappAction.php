<?php

declare(strict_types=1);

namespace App\Actions\Public\Whatsapp;

use App\Enums\WhatsappContactStatus;
use App\Models\WhatsappContact;
use App\Models\WhatsappGroup;
use App\Support\Responses\ApiResponse;
use App\Support\Whatsapp\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * اشتراك عامّ من الموقع — يُسنِد المشترك تلقائياً لمجموعة «مشتركو الموقع».
 * الوجهة: المجموعة المعلَّمة افتراضية (is_default)، وإن لم تُعلَّم أيٌّ منها بعد فأقدم
 * مجموعة (= «مشتركو الموقع» عمليّاً) — حتى لا يفشل الاشتراك لمجرّد أنّ العلم غير مضبوط.
 * E.164 (رفض المحلّي)، منع التكرار، وإعادة تفعيل من ألغى سابقاً. idempotent: رقم مشترك
 * مسبقاً ⇒ نجاح صامت (لا كشف بأنّه مسجَّل — خصوصية).
 */
class SubscribeWhatsappAction
{
    /** @param  array<string,mixed>  $data */
    public function handle(array $data): JsonResponse
    {
        $phone = PhoneNumber::normalize((string) $data['phone']);
        if ($phone === null) {
            return ApiResponse::error(__('whatsapp.public.invalid_phone'), [], 422);
        }

        // الافتراضية أوّلاً، ثمّ أقدم مجموعة كحلّ احتياطيّ (لا يفشل إلا إن لم توجد مجموعة إطلاقاً).
        $defaultGroup = WhatsappGroup::query()->default()->first()
            ?? WhatsappGroup::query()->orderBy('id')->first();
        if ($defaultGroup === null) {
            return ApiResponse::error(__('whatsapp.public.unavailable'), [], 503);
        }

        DB::transaction(function () use ($data, $phone, $defaultGroup): void {
            $contact = WhatsappContact::withTrashed()->where('phone', $phone)->first();

            if ($contact === null) {
                $contact = WhatsappContact::create([
                    'name' => $data['name'],
                    'phone' => $phone,
                    'status' => WhatsappContactStatus::Subscribed->value,
                    'source' => 'api',
                ]);
            } else {
                if ($contact->trashed()) {
                    $contact->restore();
                }
                // إعادة تفعيل من ألغى، وتحديث الاسم إن تغيّر — بلا إنشاء صفّ ثانٍ.
                $contact->status = WhatsappContactStatus::Subscribed;
                $contact->name = $data['name'];
                $contact->save();
            }

            $contact->groups()->syncWithoutDetaching([$defaultGroup->id]);
        });

        return ApiResponse::success(message: __('whatsapp.public.subscribed'), status: 201);
    }
}
