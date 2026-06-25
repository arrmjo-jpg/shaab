<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Enums\TeamMemberStatus;
use App\Http\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class ToggleTeamMemberStatusRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(TeamMemberStatus::values())],
        ];
    }
}
