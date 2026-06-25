<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

/**
 * نتيجة إعادة كتابة وسائط مستند: المستند المُعاد كتابته + عدّادات الاستيراد/إعادة
 * الاستخدام + تحذيرات الفشل المُصنَّفة (src + reason) + خريطة src→asset_id.
 */
final class MediaRewriteResult
{
    /**
     * @param  array<string,mixed>  $doc
     * @param  array<int,array{src:string,reason:string}>  $warnings
     * @param  array<string,int>  $assetBySrc
     */
    public function __construct(
        public readonly array $doc,
        public readonly int $imported,
        public readonly int $reused,
        public readonly array $warnings,
        public readonly array $assetBySrc,
    ) {}
}
