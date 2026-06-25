<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Content;

use App\Http\Requests\BaseFormRequest;
use App\Models\Article;
use Illuminate\Validation\Rule;

/**
 * فحص توفّر slug للمقال (مع لغة وتجاهل مقال حالي عند التعديل).
 */
class SlugCheckRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'slug' => ['required', 'string', 'max:255'],
            'locale' => ['required', Rule::in(Article::LOCALES)],
            'ignore_id' => ['sometimes', 'nullable', 'integer'],
        ];
    }
}
