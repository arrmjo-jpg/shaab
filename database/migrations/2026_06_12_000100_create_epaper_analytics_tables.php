<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تحليلات القارئ المجمَّعة (Phase 5) — عدّادات لكل عدد، بلا هوية مستخدم/IP (واعية
 * للخصوصية). تُغذّى من جلسة القراءة عبر وظيفة مُجدوَلة (queue-safe). أساس تقرير
 * إداريّ بسيط (لا جناح تحليلات مؤسسيّ). تُحذف بحذف العدد نهائياً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epaper_issue_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('epaper_id')->unique()->constrained('epapers')->cascadeOnDelete();
            $table->unsignedBigInteger('opens')->default(0);
            $table->unsignedBigInteger('sessions')->default(0);
            $table->unsignedBigInteger('total_duration_seconds')->default(0);
            $table->unsignedBigInteger('pages_viewed')->default(0);
            $table->unsignedBigInteger('searches')->default(0);
            $table->unsignedBigInteger('bookmarks_used')->default(0);
            $table->unsignedBigInteger('resumes_used')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();
        });

        Schema::create('epaper_page_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('epaper_id')->constrained('epapers')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['epaper_id', 'page_number']); // أكثر الصفحات مشاهدةً
        });

        Schema::create('epaper_search_terms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('epaper_id')->constrained('epapers')->cascadeOnDelete();
            $table->string('term', 100);
            $table->unsignedBigInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['epaper_id', 'term']); // أكثر عبارات البحث استخداماً
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epaper_search_terms');
        Schema::dropIfExists('epaper_page_views');
        Schema::dropIfExists('epaper_issue_stats');
    }
};
