<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسيع تحليلات القارئ للوحة المؤسّسيّة (Final completion) — إضافيّ بحت، يحفظ
 * الأنابيب القائمة (العدّادات التراكميّة في epaper_issue_stats تبقى كما هي):
 *
 *  - epaper_daily_stats: تجميعات يوميّة (تاريخ × عدد) تُمكّن تحليلات المدى الزمنيّ
 *    (اليوم/7/30/مدى مخصّص). تُملأ تقدّماً من لحظة الإطلاق (لا تلفيق تاريخيّ).
 *  - epaper_issue_stats.downloads: عدّاد تنزيلات تراكميّ لكل عدد (واقعيّ، حين يُمنح
 *    استحقاق التنزيل).
 *  - epaper_archive_search_daily: استخدام بحث الأرشيف العابر (تاريخ × لغة) — لم يكن
 *    يُتتبَّع. كلّه بلا هوية مستخدم/IP (واعٍ للخصوصية).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epaper_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('epaper_id')->constrained('epapers')->cascadeOnDelete();
            $table->date('stat_date');
            $table->unsignedBigInteger('opens')->default(0);
            $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('total_duration_seconds')->default(0);
            $table->unsignedBigInteger('pages_viewed')->default(0);
            $table->unsignedBigInteger('searches')->default(0);
            $table->unsignedBigInteger('bookmarks_used')->default(0);
            $table->unsignedBigInteger('resumes_used')->default(0);
            $table->unsignedBigInteger('downloads')->default(0);
            $table->timestamps();

            $table->unique(['epaper_id', 'stat_date']); // صفّ واحد لكل (عدد، يوم)
            $table->index('stat_date');                  // مرشّحات المدى الزمنيّ العامّة
        });

        Schema::table('epaper_issue_stats', function (Blueprint $table): void {
            $table->unsignedBigInteger('downloads')->default(0)->after('resumes_used');
        });

        Schema::create('epaper_archive_search_daily', function (Blueprint $table): void {
            $table->id();
            $table->date('stat_date');
            $table->string('locale', 5)->default('');
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['stat_date', 'locale']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epaper_archive_search_daily');
        Schema::table('epaper_issue_stats', function (Blueprint $table): void {
            $table->dropColumn('downloads');
        });
        Schema::dropIfExists('epaper_daily_stats');
    }
};
