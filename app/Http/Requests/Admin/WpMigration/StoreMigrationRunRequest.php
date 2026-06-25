<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\WpMigration;

use App\Http\Requests\BaseFormRequest;

class StoreMigrationRunRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية مفروضة عبر middleware المسار (wp-migration.manage)
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:150'],
            'db_host' => ['required', 'string', 'max:191'],
            'db_port' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'db_name' => ['required', 'string', 'max:191'],
            'db_username' => ['required', 'string', 'max:191'],
            'db_password' => ['sometimes', 'nullable', 'string', 'max:191'],
            'table_prefix' => ['sometimes', 'nullable', 'string', 'max:64'],
            'uploads_path' => ['sometimes', 'nullable', 'string', 'max:1024'],
        ];
    }
}
