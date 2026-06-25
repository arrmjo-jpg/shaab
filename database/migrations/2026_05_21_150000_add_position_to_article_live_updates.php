<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ترتيب تحريري صريح لتحديثات التغطية الحيّة.
 *
 * نموذج الترتيب: المثبَّت أولاً ثم position تنازلياً (الأعلى = الأحدث/المرفوع).
 * يُهيّأ position = id للصفوف القائمة (يحفظ الترتيب الزمني)، والإنشاء يضع
 * max(position)+1 فيظهر الجديد أعلى الخط. النقل لأعلى/أسفل يبدّل المواضع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_live_updates', function (Blueprint $table): void {
            $table->unsignedBigInteger('position')->default(0)->after('is_featured');
            $table->index(['article_id', 'position'], 'live_updates_position_idx');
        });

        // تهيئة المواضع للصفوف القائمة بحيث يطابق الترتيب الزمني (id تصاعدي).
        DB::statement('UPDATE article_live_updates SET position = id');
    }

    public function down(): void
    {
        Schema::table('article_live_updates', function (Blueprint $table): void {
            $table->dropIndex('live_updates_position_idx');
            $table->dropColumn('position');
        });
    }
};
