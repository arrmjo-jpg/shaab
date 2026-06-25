<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * السطح العام للبثّ (B4): أعمدة SEO أصلية + ربط VOD اختياري.
 *
 * SEO يطابق نمط المقال/الفيديو (seo_title/seo_description/seo_keywords/canonical_url/
 * robots) — مصدر الحقيقة للوسوم العامة. vod_video_id رابط اختياري لتسجيلٍ نهائي في
 * مكتبة الفيديو بعد انتهاء الحدث: البثّ يبقى نطاقاً مستقلّاً (nullOnDelete، لا اقتران
 * بنيوي) — حذف الفيديو يُفرّغ الرابط فقط دون كسر البثّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            // SEO أصلي (مرآة المقال/الفيديو) — أطوال مطابقة لجدول الفيديو
            $table->string('seo_title', 255)->nullable()->after('poster_path');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->string('seo_keywords', 255)->nullable()->after('seo_description');
            $table->string('canonical_url', 255)->nullable()->after('seo_keywords');
            $table->string('robots', 50)->nullable()->after('canonical_url');

            // ربط VOD اختياري — تسجيل نهائي في مكتبة الفيديو (نطاق مستقل، nullOnDelete)
            $table->foreignId('vod_video_id')->nullable()->after('category_id')
                ->constrained('videos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('vod_video_id');
            $table->dropColumn(['seo_title', 'seo_description', 'seo_keywords', 'canonical_url', 'robots']);
        });
    }
};
