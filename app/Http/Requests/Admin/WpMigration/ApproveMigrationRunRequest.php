<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\WpMigration;

use App\Enums\ConflictPolicy;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ApproveMigrationRunRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة عبر middleware المسار (wp-migration.manage)
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'conflict_policy' => ['required', Rule::enum(ConflictPolicy::class)],
        ];
    }
}
