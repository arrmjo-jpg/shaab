<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use App\Enums\ArticleType;

/**
 * نتيجة حسم تصنيفات منشور: النوع النهائي + التصنيف الرئيسي + الثانوية (فريدة، بلا حدّ).
 * كلها من نوع متوافق واحد (لا احتفاظ بنوع مختلط بعد تطبيق سياسة التعارض).
 */
final class ResolvedCategories
{
    /** @param  array<int,int>  $secondary */
    public function __construct(
        public readonly ArticleType $type,
        public readonly int $primary,
        public readonly array $secondary,
    ) {}
}
