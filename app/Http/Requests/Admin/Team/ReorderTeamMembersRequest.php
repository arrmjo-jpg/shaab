<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Team;

use App\Http\Requests\BaseFormRequest;

class ReorderTeamMembersRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'distinct', 'exists:team_members,id'],
        ];
    }
}
