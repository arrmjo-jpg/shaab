<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعمدة المراقبة (Phase 7): خطّ زمنيّ لدورة الحياة + عدّادات وسائط حتمية لكل عنصر
 * (تُجمَع بـ SUM رخيص للوحة الحيّة بدل مسح JSON على 84k+) + عنوان المصدر لفحص الفشل.
 * أمامية فقط — أعمدة قابلة للإفراغ/بقيمة افتراضية، آمنة على جدول قائم.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('wp_migration_runs', function (Blueprint $table): void {
            // سجلّ أحداث دورة الحياة: [{event, at}] — started/paused/resumed/stopping/completed/retried.
            $table->json('timeline')->nullable();
            // لقطة عدّاد وسائط فاشلة على مستوى التشغيلة (تُختم عند الاكتمال).
            $table->unsignedInteger('media_failed')->default(0);
        });

        Schema::table('wp_migration_items', function (Blueprint $table): void {
            // عنوان المنشور المصدري — يُلتقط عند نجاح القراءة لفحص الفشل (#4) حتى دون مقال.
            $table->string('source_title')->nullable();
            // عدّادات وسائط لكل عنصر (مصدر مجاميع اللوحة الحيّة — فهرس run_id يكفي).
            $table->unsignedInteger('media_imported')->default(0);
            $table->unsignedInteger('media_reused')->default(0);
            $table->unsignedInteger('media_failed')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('wp_migration_runs', function (Blueprint $table): void {
            $table->dropColumn(['timeline', 'media_failed']);
        });

        Schema::table('wp_migration_items', function (Blueprint $table): void {
            $table->dropColumn(['source_title', 'media_imported', 'media_reused', 'media_failed']);
        });
    }
};
