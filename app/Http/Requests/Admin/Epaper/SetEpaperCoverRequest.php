<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin\Epaper;

use App\Http\Requests\BaseFormRequest;

/**
 * رفع غلاف العدد يدوياً (صورة). يُخزَّن في media_asset.conversions['cover'] — نفس فتحة
 * الغلاف المُولَّد تلقائياً، فيظهر فورًا في الواجهة عبر cover_url (لا تلفيق، لا اعتماد على pdftoppm).
 */
class SetEpaperCoverRequest extends BaseFormRequest
{
    public function authorize(): bool
    {
        return true; // الصلاحية عبر middleware المسار (epapers.edit)
    }

    /** @return array<string,mixed> */
    public function rules(): array
    {
        $maxKb = (int) config('performance.media.image_max_kb', 5120);

        return [
            'cover' => ['required', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:'.$maxKb],
        ];
    }
}
