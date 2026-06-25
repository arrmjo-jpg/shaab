<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. reels (نوع محتوى من الدرجة الأولى) ───────────────────────
        Schema::create('reels', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            // هوية موحّدة (جدول users) — تبقى عند حذف المستخدم
            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // فيديو الريل = أصل واحد من media_assets (تُربط في المرحلة 3)
            $table->foreignId('media_asset_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();

            $table->string('status', 20)->default('draft')->index();
            $table->string('locale', 10)->index();
            $table->uuid('translation_group')->nullable()->index();

            $table->string('title', 200);
            $table->string('slug', 190);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // SEO أصلي (أعمدة هي مصدر الحقيقة — نفس نمط المقالات)
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords', 255)->nullable();
            $table->string('canonical_url', 255)->nullable();
            $table->string('robots', 50)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable()->index();

            $table->softDeletes();
            $table->timestamps();

            // slug فريد لكل لغة (يشمل المحذوف منطقياً عبر فحص الجدول)
            $table->unique(['locale', 'slug']);

            // فهارس مُوجَّهة لأنماط الاستعلام (نطاق مستقل — لا تصنيفات)
            $table->index(['status', 'locale', 'published_at'], 'reels_status_locale_pub_idx');
            $table->index(['deleted_at', 'status', 'published_at'], 'reels_deleted_status_pub_idx');
        });

        // ─── 2. reel_revisions (لقطات غير قابلة للتعديل — append-only) ────
        Schema::create('reel_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reel_id')->constrained('reels')->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('description')->nullable();
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords', 255)->nullable();
            $table->string('status_snapshot', 20);
            $table->json('meta_snapshot')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['reel_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_revisions');
        Schema::dropIfExists('reels');
    }
};
