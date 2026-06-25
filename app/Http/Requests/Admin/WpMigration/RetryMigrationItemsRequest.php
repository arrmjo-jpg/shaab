<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\WpMigration;

use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class RetryMigrationItemsRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة عبر middleware المسار (wp-migration.manage)
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            // selected: عناصر مُحدَّدة بالمُعرّفات؛ failed/partial: كل عناصر التشغيلة بتلك الحالة.
            'mode' => ['required', Rule::in(['selected', 'failed', 'partial'])],
            'ids' => ['required_if:mode,selected', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer'],
        ];
    }
}
