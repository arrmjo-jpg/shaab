<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * استيراد التصنيفات (Phase 9): يفصل محور «التصرّف» (إنشاء/ربط/استثناء) عن نوع
 * المحتوى (WpCategoryMode). create_category_id يربط الصفّ بالتصنيف المُنشأ —
 * هو علامة المنشأ الترحيليّ ووصلة الاسترجاع (يبقى core categories سليماً).
 * أمامية فقط + متوافقة: الصفوف القائمة المُضمَّنة (mode != exclude) تُرحَّل إلى map.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wp_migration_category_maps', function (Blueprint $table): void {
            // محور التصرّف الصريح: create | map | exclude (WpCategoryDisposition).
            $table->string('disposition', 10)->default('exclude');
            // التصنيف المُنشأ لهذا الصفّ (idempotency + تتبّع المنشأ للاسترجاع).
            $table->foreignId('created_category_id')->nullable()->constrained('categories')->nullOnDelete();
        });

        // توافق رجعيّ: أيّ تنسيب مُضمَّن سابق (mode != exclude) كان ربطاً بقائم.
        DB::table('wp_migration_category_maps')->where('mode', '!=', 'exclude')->update(['disposition' => 'map']);
    }

    public function down(): void
    {
        Schema::table('wp_migration_category_maps', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_category_id');
            $table->dropColumn('disposition');
        });
    }
};
