<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حالة القارئ لكل مستخدم مُصادَق (Phase 5): متابعة القراءة (آخر صفحة) + الإشارات
 * المرجعية. الزوّار يستخدمون localStorage (لا صفوف هنا). يُحذف بحذف المستخدم أو العدد.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epaper_reading_progress', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('epaper_id')->constrained('epapers')->cascadeOnDelete();
            $table->unsignedInteger('last_page')->default(1);
            $table->timestamps();

            $table->unique(['user_id', 'epaper_id']); // تقدّم واحد لكل (مستخدم، عدد)
        });

        Schema::create('epaper_bookmarks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('epaper_id')->constrained('epapers')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->timestamps();

            $table->unique(['user_id', 'epaper_id', 'page_number']);
            $table->index(['user_id', 'epaper_id']); // قائمة إشارات المستخدم لعددٍ ما
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epaper_bookmarks');
        Schema::dropIfExists('epaper_reading_progress');
    }
};
