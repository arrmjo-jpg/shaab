<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\VideoLibrary;

use App\Enums\VideoStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

/**
 * انتقال حالة الفيديو. النشر/الجدولة يفرضان حارس الوسائط الجاهزة في الـ Action.
 */
class TransitionVideoRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(VideoStatus::values())],
            // مطلوب عند الجدولة (مستقبلاً)؛ يُتحقَّق منطقياً في الـ Action.
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
