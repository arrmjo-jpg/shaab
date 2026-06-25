<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Chat;

use App\Http\Requests\BaseFormRequest;

class SendMessageRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // نصّ أو مرفق (أحدهما على الأقل). body نصّ صِرف — لا HTML.
            'body' => ['required_without:attachment_asset_id', 'nullable', 'string', 'max:5000'],
            'attachment_asset_id' => ['nullable', 'integer', 'exists:media_assets,id'],
        ];
    }
}
