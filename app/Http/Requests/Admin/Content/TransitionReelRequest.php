<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Enums\ReelStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class TransitionReelRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(ReelStatus::values())],
            // وقت الجدولة — مطلوب فقط عند الانتقال إلى «مجدول».
            'published_at' => ['nullable', 'date', 'required_if:status,scheduled'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v): void {
            if (
                $this->input('status') === ReelStatus::Scheduled->value
                && ! empty($this->input('published_at'))
                && Carbon::parse($this->input('published_at'))->isPast()
            ) {
                $v->errors()->add('published_at', __('reel.schedule_future'));
            }
        });
    }
}
