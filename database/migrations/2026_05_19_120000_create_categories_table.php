<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()
                ->constrained('categories')->nullOnDelete();
            $table->string('locale', 10)->index();
            $table->uuid('translation_group')->nullable()->index();
            $table->string('name', 150);
            $table->string('slug', 160);
            $table->text('description')->nullable();
            $table->string('icon', 100)->nullable();
            $table->string('status', 20)->default('active');
            $table->boolean('show_in_header')->default(false);
            $table->boolean('show_in_body')->default(true);
            $table->boolean('show_in_footer')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->softDeletes();
            $table->timestamps();

            // Slug فريد ضمن نفس اللغة (ADR A3.4 / slug per-locale)
            $table->unique(['locale', 'slug']);

            // فهارس مُوجَّهة لأنماط الاستعلام الفعلية
            $table->index(['status', 'locale', 'sort_order'], 'categories_status_locale_order_idx');
            $table->index(['parent_id', 'sort_order'], 'categories_parent_order_idx');
            $table->index(['deleted_at', 'locale'], 'categories_deleted_locale_idx');
        });

        // لا حارس CHECK على مستوى القاعدة لمنع الأب-الذاتي: MySQL 8 يرفض استخدام
        // عمود مفتاح أجنبي ذي إجراء مرجعي (parent_id, nullOnDelete) داخل CHECK
        // (الخطأ 3823). منع الأب-الذاتي/الدائري مفروض في CategoryHierarchyGuard
        // (يُستدعى في CreateCategoryAction + UpdateCategoryAction).
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
