<?php

declare(strict_types=1);

namespace App\Actions\Public\Account;

use App\Actions\Public\Whatsapp\SubscribeWhatsappAction;
use App\Enums\WhatsappContactStatus;
use App\Http\Resources\Public\UserResource;
use App\Models\User;
use App\Models\WhatsappContact;
use App\Support\Responses\ApiResponse;
use App\Support\Whatsapp\PhoneNumber;
use Illuminate\Http\JsonResponse;

/**
 * حفظ رقم هاتف المستخدم + اختيار الاشتراك في حملات واتساب (نافذة ما بعد الدخول).
 *
 * SSoT: الاشتراك الفعليّ (الذي يقرؤه مُرسِل الحملات) يعيش في whatsapp_contacts؛ وعمود
 * users.whatsapp_subscribed مرآة موافقة المستخدم. لذلك بعد حفظ بيانات المستخدم نزامن الخيار
 * مع النظام القائم: التفعيل يعيد استخدام SubscribeWhatsappAction (upsert + المجموعة الافتراضية
 * + إعادة تفعيل)، والإلغاء يضبط status=unsubscribed لرقم المستخدم (لا حذف) — فلا يكون العَلَم ميّتاً.
 */
class UpdateUserPhoneAction
{
    /** @param  array<string,mixed>  $data */
    public function handle(User $user, array $data): JsonResponse
    {
        $phone = PhoneNumber::normalize((string) ($data['phone'] ?? ''));
        if ($phone === null) {
            return ApiResponse::error(__('account.phone.invalid'), [], 422);
        }

        $subscribed = (bool) ($data['whatsapp_subscribed'] ?? false);

        $user->update([
            'phone' => $phone,
            'whatsapp_subscribed' => $subscribed,
        ]);

        if ($subscribed) {
            // إعادة استخدام منطق الاشتراك العامّ. الاستجابة تُهمَل عمداً — الأثر (إنشاء/تفعيل جهة
            // الاتصال وإسنادها للمجموعة الافتراضية) هو المطلوب، والرقم مُطبَّع مسبقاً فلن يُرفَض.
            (new SubscribeWhatsappAction)->handle(['name' => $user->name, 'phone' => $phone]);
        } else {
            WhatsappContact::query()
                ->where('phone', $phone)
                ->where('status', '!=', WhatsappContactStatus::Unsubscribed->value)
                ->get()
                ->each(function (WhatsappContact $contact): void {
                    $contact->status = WhatsappContactStatus::Unsubscribed;
                    $contact->save();
                });
        }

        return ApiResponse::success(
            message: __('account.phone.saved'),
            data: new UserResource($user->fresh()),
        );
    }
}
