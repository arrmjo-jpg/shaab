<?php

declare(strict_types=1);

namespace App\Http\Requests\Public\Advertising;

use App\Http\Requests\BaseFormRequest;

/**
 * منارة النقرة (V2) — لإبداعات HTML التي تملك روابطها الخاصّة، فتُحتسب نقرتها بمنارة
 * عميل (لا تمرّ بتحويل النقرة الموقّع كإبداعات الصورة). الرمز وحده يحمل (placement,
 * zone, bucket)؛ يُفكّ ويُتحقَّق منه في الـ Action. عام (لا تفويض).
 */
class TrackAdClickRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:512'],
        ];
    }
}
