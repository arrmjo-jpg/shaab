<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * فهارس ترتيب استباقيّة لجداول النموّ (reels/videos/broadcasts/ads/epapers).
 *
 * هذه الجداول صغيرة الآن لكنها ستكبر (الأخبار وصلت 79k فظهر فيها بطء فهرس مفقود).
 * كلها مفهرسة جيداً على الفلاتر، لكن **لا فهرس فيها ينتهي بـ created_at** — وكل
 * قوائمها الإدارية ترتّب `created_at DESC` (الأعداد الرقمية ترتّب publication_date).
 * بلا فهرس مطابق → filesort عند النموّ (نفس فخّ الأخبار 15.9s).
 *
 * إضافة الآن فوريّة وصفر مخاطرة (جداول شبه فارغة) وتجهّز المخطّط قبل وصول البيانات.
 * فهرس واحد دقيق لكل نمط (filter-prefix + sort-suffix DESC) — لا تكديس.
 * DESC حقيقيّ (MySQL 8) → SQL خام، محصور بـ mysql (الاختبارات على SQLite تتجاوزه).
 */
return new class extends Migration
{
    /** table => [indexName => 'col1, col2 DESC', ...] */
    private const INDEXES = [
        'reels' => [
            'reels_deleted_created_idx' => 'deleted_at, created_at DESC',
        ],
        'videos' => [
            'videos_deleted_created_idx' => 'deleted_at, created_at DESC',
            'videos_cat_deleted_created_idx' => 'video_category_id, deleted_at, created_at DESC',
        ],
        'broadcasts' => [
            'broadcasts_deleted_created_idx' => 'deleted_at, created_at DESC',
        ],
        'ad_campaigns' => [
            'ad_campaigns_deleted_created_idx' => 'deleted_at, created_at DESC',
        ],
        'ad_creatives' => [
            // ad_creatives بلا SoftDeletes — الفلتر الأساسيّ بالحملة + الترتيب.
            'ad_creatives_campaign_created_idx' => 'ad_campaign_id, created_at DESC',
        ],
        'ad_placements' => [
            // بلا SoftDeletes — الفلتر بالمنطقة + الترتيب.
            'ad_placements_zone_created_idx' => 'ad_zone_id, created_at DESC',
        ],
        'epapers' => [
            // ترتيبه الافتراضي publication_date DESC لا created_at.
            'epapers_deleted_pubdate_idx' => 'deleted_at, publication_date DESC',
        ],
    ];

    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($indexes as $name => $cols) {
                if (! $this->hasIndex($table, $name)) {
                    DB::statement("CREATE INDEX {$name} ON {$table} ({$cols})");
                }
            }
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::INDEXES as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach (array_keys($indexes) as $name) {
                if ($this->hasIndex($table, $name)) {
                    Schema::table($table, fn ($t) => $t->dropIndex($name));
                }
            }
        }
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $i): bool => $i['name'] === $index);
    }
};
