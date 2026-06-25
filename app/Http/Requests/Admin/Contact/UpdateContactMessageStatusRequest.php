<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Contact;

use App\Enums\ContactMessageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * تغيير حالة رسالة اتصال يدويّاً (الصلاحية على المسار). الأهداف المسموحة: in_review / closed
 * (وإعادة الفتح = closed→in_review). «replied» يُضبَط حصراً عبر تدفّق الردّ؛ «new» حالة أوّليّة.
 */
class UpdateContactMessageStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in([
                ContactMessageStatus::InReview->value,
                ContactMessageStatus::Closed->value,
            ])],
        ];
    }
}
