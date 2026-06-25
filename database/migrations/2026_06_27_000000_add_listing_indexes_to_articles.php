<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فهارس أداء لقائمة الأخبار وعدّادات الأقسام على جدول الأخبار (~79k صفّ).
 *
 * - (deleted_at, created_at): القائمة الإدارية الافتراضية ترتّب بـ created_at desc
 *   مع استبعاد المحذوف. بلا هذا الفهرس كان MySQL يقرأ ~11.6k صفّ ويرتّبها filesort
 *   (قياس فعليّ: 15.9 ثانية للصفحة الواحدة) — مع الفهرس يصبح ترتيباً مفهرساً (~ms).
 * - (primary_category_id, deleted_at): عدّ مقالات كل قسم (رئيسي). يزيل
 *   "Using temporary" من GROUP BY (كان 6.2 ثانية).
 *
 * إضافة فهارس فقط — لا تعديل بيانات. idempotent عبر فحص الوجود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            if (! $this->hasIndex('articles', 'articles_deleted_created_idx')) {
                $table->index(['deleted_at', 'created_at'], 'articles_deleted_created_idx');
            }
            if (! $this->hasIndex('articles', 'articles_pcat_deleted_idx')) {
                $table->index(['primary_category_id', 'deleted_at'], 'articles_pcat_deleted_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            if ($this->hasIndex('articles', 'articles_deleted_created_idx')) {
                $table->dropIndex('articles_deleted_created_idx');
            }
            if ($this->hasIndex('articles', 'articles_pcat_deleted_idx')) {
                $table->dropIndex('articles_pcat_deleted_idx');
            }
        });
    }

    private function hasIndex(string $table, string $index): bool
    {
        return collect(Schema::getIndexes($table))
            ->contains(fn (array $i): bool => $i['name'] === $index);
    }
};
