<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تصنيفات مكتبة الفيديو — تصنيف مستقل خاص بنطاق الفيديو (ليس تصنيفات الأخبار).
 * شجرة هرمية بسيطة (parent_id) + slug فريد لكل لغة.
 *
 * منع الأب-الذاتي/الدوري وحدّ العمق يُفرَض بالكامل في الـ Action (المرحلة 4) —
 * لا قيد CHECK على مستوى DB لأنّ MySQL 8 يمنع CHECK على عمود مشمول بمفتاح أجنبي
 * ذي إجراء مرجعي SET NULL (خطأ 3823). يعيد استخدام أنماط Category دون أعمدة
 * خاصّة بالأخبار (header/body/footer).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->constrained('video_categories')->nullOnDelete();
            $table->string('locale', 10)->index();
            $table->uuid('translation_group')->nullable()->index();
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            // صورة غلاف اختيارية من مكتبة الوسائط المركزية (لا تخزين موازٍ)
            $table->foreignId('cover_media_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            // SEO أصلي (أعمدة مصدر الحقيقة — نفس نمط بقية النطاقات)
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['locale', 'slug']);
            $table->index(['is_active', 'locale', 'sort_order'], 'video_cats_active_locale_order_idx');
            $table->index(['parent_id', 'sort_order'], 'video_cats_parent_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('video_categories');
    }
};
