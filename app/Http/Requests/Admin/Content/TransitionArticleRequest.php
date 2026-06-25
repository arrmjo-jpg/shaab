<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Enums\ArticleStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class TransitionArticleRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(ArticleStatus::values())],
            // مطلوب فقط للجدولة — يُتحقَّق من المستقبلية في الحارس أيضاً
            'published_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
