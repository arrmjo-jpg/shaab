<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تحسين فهرسة جدول الأخبار (~79k صفّ، MySQL 8.4 InnoDB) — "أقوى فهرسة" = فهارس
 * مركّبة قليلة تطابق أنماط الاستعلام الحقيقية + إزالة الزائد، لا تكديس فهارس.
 *
 * المبدأ:
 *  - الترتيب الافتراضي للقائمة (deleted_at IS NULL ORDER BY created_at DESC) يحتاج
 *    فهرساً تنازليّاً حقيقياً (مدعوم في MySQL 8) → صفر filesort.
 *  - الفلتر الأكثر استخداماً = القسم؛ فهرس مركّب (pcat, deleted, created DESC) يخدم
 *    الفلترة + الترتيب + العدّ في بنية واحدة (covering للترتيب).
 *  - البحث بالعنوان كان LIKE '%...%' = مسح كامل دائماً؛ FULLTEXT بمحلّل ngram
 *    (token=2، مناسب للعربي) يحوّله لبحث مفهرس.
 *  - فهارس مفردة ميّتة/مكرّرة تُسقط: إمّا cardinality=1 بلا فائدة قراءة (status,
 *    locale, is_*) أو بادئة مركّب أطول تغطّيها (type, published_at). كلها عبء
 *    كتابة + قرص صِرف. (نُبقي المركّبات *_status_pub_idx لخدمة القراءة العامة.)
 *
 * idempotent عبر فحص الوجود. إضافة/إسقاط فهارس فقط — لا تعديل بيانات.
 */
return new class extends Migration
{
    /**
     * فهارس مفردة يغطّيها مركّب أطول أو ميّتة (cardinality=1).
     * ملاحظة: is_breaking/is_editor_pick أُزيلت من هذه القائمة — تُفلتَر بـ =true
     * (قيمة نادرة) فالفهرس انتقائيّ ومفيد؛ يُعاد ضمانها في
     * 2026_06_28_index_article_flag_columns. is_featured يبقى (مغطّى بالمركّب
     * articles_featured_pub_idx الذي يقوده، فالمفرد زائد).
     */
    private const DROP = [
        'articles_type_index',
        'articles_status_index',
        'articles_locale_index',
        'articles_is_featured_index',
        'articles_published_at_index',
        // نسخة غير تنازليّة — تُستبدَل بـ DESC أدناه.
        'articles_deleted_created_idx',
    ];

    public function up(): void
    {
        // فهارس تنازليّة + FULLTEXT(ngram) تركيب خاصّ بـ MySQL؛ تُتجاوَز على محرّكات
        // أخرى (SQLite في الاختبارات — جداول صغيرة لا تحتاجها أصلاً).
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        foreach (self::DROP as $name) {
            $this->dropIndexIfExists('articles', $name);
        }

        // فهارس B-tree تنازليّة (MySQL 8) عبر SQL خام — Blueprint لا يُعبّر عن DESC.
        if (! $this->hasIndex('articles', 'articles_deleted_created_desc_idx')) {
            DB::statement('CREATE INDEX articles_deleted_created_desc_idx ON articles (deleted_at, created_at DESC)');
        }
        if (! $this->hasIndex('articles', 'articles_pcat_deleted_created_idx')) {
            DB::statement('CREATE INDEX articles_pcat_deleted_created_idx ON articles (primary_category_id, deleted_at, created_at DESC)');
        }

        // المركّب الأقدم (pcat, deleted) صار بادئة المركّب الأطول أعلاه → يُسقط.
        $this->dropIndexIfExists('articles', 'articles_pcat_deleted_idx');

        // FULLTEXT عربي للبحث بالعنوان (ngram).
        if (! $this->hasIndex('articles', 'articles_title_fulltext')) {
            DB::statement('CREATE FULLTEXT INDEX articles_title_fulltext ON articles (title) WITH PARSER ngram');
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            return;
        }

        $this->dropIndexIfExists('articles', 'articles_title_fulltext');
        $this->dropIndexIfExists('articles', 'articles_pcat_deleted_created_idx');
        $this->dropIndexIfExists('articles', 'articles_deleted_created_desc_idx');

        Schema::table('articles', function ($table): void {
            $table->index(['deleted_at', 'created_at'], 'articles_deleted_created_idx');
            $table->index(['primary_category_id', 'deleted_at'], 'articles_pcat_deleted_idx');
            $table->index(['type'], 'articles_type_index');
            $table->index(['status'], 'articles_status_index');
            $table->index(['locale'], 'articles_locale_index');
            $table->index(['is_featured'], 'articles_is_featured_index');
            $table->index(['published_at'], 'articles_published_at_index');
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $i): bool => $i['name'] === $index);
    }

    private function dropIndexIfExists(string $table, string $index): void
    {
        if ($this->hasIndex($table, $index)) {
            Schema::table($table, fn ($t) => $t->dropIndex($index));
        }
    }
};
