<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\WpMigration;

use App\Enums\WpCategoryDisposition;
use App\Enums\WpCategoryMode;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class SaveCategoryMapsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة عبر middleware المسار (wp-migration.manage)
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'maps' => ['required', 'array', 'min:1'],
            'maps.*.wp_term_id' => ['required', 'integer'],
            'maps.*.wp_name' => ['required', 'string', 'max:191'],
            'maps.*.wp_slug' => ['sometimes', 'nullable', 'string', 'max:191'],
            'maps.*.wp_parent_id' => ['sometimes', 'nullable', 'integer'],
            'maps.*.wp_count' => ['sometimes', 'integer', 'min:0'],
            'maps.*.mode' => ['required', Rule::enum(WpCategoryMode::class)],
            // اختياري للتوافق الرجعيّ: غيابه يُستنتَج (مُضمَّن ⇒ map، وإلا exclude).
            'maps.*.disposition' => ['sometimes', Rule::enum(WpCategoryDisposition::class)],
            // مطلوب فعلياً لـ map فقط — يُفرَض دلالياً في الـ Action.
            'maps.*.target_category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }
}
