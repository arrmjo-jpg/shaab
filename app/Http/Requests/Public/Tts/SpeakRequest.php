<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Tts;

use Illuminate\Foundation\Http\FormRequest;

/**
 * طلب توليد صوت من نصّ المقال (عامّ). الحماية على المسار (throttle:public.tts). حدّ الطول يكبح
 * الكلفة/زمن الاستجابة (Gemini مدفوع). الميزة نفسها محكومة بإعدادات Spatie داخل الـAction.
 */
class SpeakRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'min:1', 'max:5000'],
        ];
    }
}
