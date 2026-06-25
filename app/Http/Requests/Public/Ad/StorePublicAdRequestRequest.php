<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Ad;

use App\Enums\AdType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * إنشاء طلب إعلان (عام) — تحقّق الحقول فقط. الحماية (recaptcha:ad_request + throttle) على المسار.
 * ad_type اختيار إلزاميّ (image|html، لا نصّ حرّ). المرفق إلزاميّ ويُحدَّد نوعه بنوع الإعلان:
 *   image ⇒ صورة (jpeg/png/webp)، html ⇒ ZIP فقط (يُحفَظ كمرفق خامّ — لا فكّ ضغط/تنفيذ). website اختياريّ.
 */
class StorePublicAdRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $type = (string) $this->input('ad_type');

        // تحقّق مزدوج: mimes (نوع المحتوى المُخمَّن) + extensions (الامتداد المُرسَل) — دفاع ضدّ
        // تزييف الـMIME/الامتداد في الاتّجاهين. الحجم محدود لكلّ نوع.
        $attachment = ['required', 'file'];
        if ($type === AdType::Image->value) {
            $attachment[] = 'image';
            $attachment[] = 'mimes:jpeg,jpg,png,webp';
            $attachment[] = 'extensions:jpeg,jpg,png,webp';
            $attachment[] = 'max:'.(int) config('performance.media.image_max_kb', 5120);
        } elseif ($type === AdType::Html->value) {
            // ZIP فقط — يُخزَّن كمرفق خامّ على القرص الخاصّ (لا فكّ ضغط/تحليل/تنفيذ).
            $attachment[] = 'mimes:zip';
            $attachment[] = 'extensions:zip';
            $attachment[] = 'max:'.(int) config('ad_request.attachment.zip_max_kb', 20480);
        }

        return [
            'company_name' => ['required', 'string', 'max:160'],
            'contact_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['required', 'string', 'max:30'],
            'website' => ['nullable', 'url', 'max:200'],
            'ad_type' => ['required', 'string', Rule::in(AdType::values())],
            'description' => ['required', 'string', 'min:5', 'max:5000'],
            'attachment' => $attachment,
        ];
    }
}
