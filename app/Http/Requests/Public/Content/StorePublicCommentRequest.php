<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Content;

use Illuminate\Foundation\Http\FormRequest;

/**
 * إنشاء تعليق/رد عام — تحقّق فقط. الزائر (بلا مصادقة) يزوّد اسماً وبريداً؛ المستخدم
 * المُصادَق يُسنَد تلقائياً (تُتجاهَل حقول الزائر في الـAction). `parent_id` اختياريّ ⇒ رد
 * (صحّته تُتحقَّق في الـAction: نفس المقال + أعلى-مستوى + معتمَد).
 */
class StorePublicCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        // المسار العام بلا auth:sanctum والـguard الافتراضيّ web لا يحلّ Bearer ⇒ نحلّ
        // المستخدم عبر guard sanctum صراحةً (وإلا عُومل المسجّل كزائر فطُلبت منه الاسم/البريد).
        $isGuest = $this->user('sanctum') === null;

        return [
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => ['nullable', 'integer'],
            'author_name' => [$isGuest ? 'required' : 'nullable', 'string', 'max:120'],
            'author_email' => [$isGuest ? 'required' : 'nullable', 'email', 'max:190'],
        ];
    }
}
