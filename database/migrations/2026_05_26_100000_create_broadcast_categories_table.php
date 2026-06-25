<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تصنيفات البثّ — تصنيف مسطّح (FLAT، لا تشجير) خاص بنطاق البثّ. عربي فقط (لا locale).
 * يعيد استخدام أنماط video_categories دون الهرمية/التعدّد اللغوي (لا parent_id/locale).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            // صورة غلاف اختيارية من مكتبة الوسائط المركزية (لا تخزين موازٍ)
            $table->foreignId('cover_media_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique('slug');
            $table->index(['is_active', 'sort_order'], 'broadcast_cats_active_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcast_categories');
    }
};
