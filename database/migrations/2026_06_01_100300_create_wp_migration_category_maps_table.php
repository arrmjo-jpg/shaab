<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_migration_category_maps', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('wp_migration_runs')->cascadeOnDelete();

            // لقطة تصنيف المصدر (الاسم/المعرّف منزوع الترميز + العدّ + الهرمية).
            $table->unsignedBigInteger('wp_term_id');
            $table->string('wp_name', 191);
            $table->string('wp_slug', 191)->nullable();
            $table->unsignedBigInteger('wp_parent_id')->nullable();
            $table->unsignedInteger('wp_count')->default(0);

            // وضع التنسيب الصريح: exclude|news|articles (WpCategoryMode).
            $table->string('mode', 20)->default('exclude');

            // الهدف الوحيد في مجمّع AlphaCMS المطابق للوضع (news→scope news/both،
            // articles→scope opinion/both). كل تصنيف مصدر يُسنَد لنوع واحد فقط.
            $table->foreignId('target_category_id')->nullable()->constrained('categories')->nullOnDelete();

            $table->timestamps();

            $table->unique(['run_id', 'wp_term_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_migration_category_maps');
    }
};
