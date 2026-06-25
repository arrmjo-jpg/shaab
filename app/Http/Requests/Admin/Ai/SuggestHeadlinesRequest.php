<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Ai;

use App\Enums\ArticleType;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

class SuggestHeadlinesRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'nullable', 'string', 'max:300'],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:300'],
            'excerpt' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'body' => ['sometimes', 'nullable', 'string', 'max:20000'],
            'type' => ['sometimes', 'nullable', Rule::in(ArticleType::values())],
            'categories' => ['sometimes', 'array', 'max:10'],
            'categories.*' => ['string', 'max:120'],
            'locale' => ['sometimes', 'nullable', 'string', 'in:ar,en'],
        ];
    }

    /** يجب توفّر عنوان أو متن على الأقل لإعطاء سياق مفيد. */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v): void {
            if (trim((string) $this->input('title')) === '' && trim((string) $this->input('body')) === '') {
                $v->errors()->add('body', __('ai.context_required'));
            }
        });
    }
}
