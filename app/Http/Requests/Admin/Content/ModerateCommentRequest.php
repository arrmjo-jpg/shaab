<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Enums\CommentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * إشراف على تعليق — تحقّق فقط (الصلاحية comments.approve عبر middleware المسار).
 * الانتقال يخرج من pending إلى حالة إشراف نهائية؛ pending ليس هدفاً صالحاً.
 */
class ModerateCommentRequest extends FormRequest
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
                CommentStatus::Approved->value,
                CommentStatus::Rejected->value,
                CommentStatus::Spam->value,
            ])],
        ];
    }
}
