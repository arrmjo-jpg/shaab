<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\MediaAsset;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * حارس ملكيّة الوسائط (IDOR): يضمن أن media_asset_id موجود ومملوك للمستخدم الحالي
 * (media_assets.uploaded_by). يُستخدَم في نماذج الكاتب عند ربط أصل بمحتوى، فلا يربط
 * الكاتب أصلاً لا يملكه. nullable يُعالَج عبر قواعد أخرى (هذه الحارس يتجاهل الفارغ).
 */
class OwnedMediaAsset implements ValidationRule
{
    public function __construct(private readonly ?int $userId) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null || $value === '' || $this->userId === null) {
            return;
        }

        $owns = MediaAsset::query()
            ->whereKey($value)
            ->where('uploaded_by', $this->userId)
            ->exists();

        if (! $owns) {
            $fail(__('validation.exists', ['attribute' => $attribute]));
        }
    }
}
