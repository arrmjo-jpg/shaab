<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ─── 1. articles ─────────────────────────────────────────────────
        Schema::create('articles', function (Blueprint $table): void {
            $table->id();

            // هوية موحّدة (لا admins/writers) — تبقى عند حذف المستخدم
            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // تصنيف رئيسي إلزامي (ADR A3.1) — منع حذف تصنيف مستخدَم
            $table->foreignId('primary_category_id')
                ->constrained('categories')->restrictOnDelete();

            $table->string('type', 20)->index();
            $table->string('status', 20)->default('draft')->index();

            $table->string('locale', 10)->index();
            $table->uuid('translation_group')->nullable()->index();

            $table->string('title', 200);
            $table->string('subtitle', 250)->nullable();
            $table->string('slug', 190);
            $table->string('short_url', 100)->nullable();
            $table->text('excerpt')->nullable();
            $table->longText('content');

            // SEO أصلي (ADR D4 — أعمدة هي مصدر الحقيقة)
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords', 255)->nullable();
            $table->string('canonical_url', 255)->nullable();
            $table->string('robots', 50)->nullable();

            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_breaking')->default(false)->index();
            $table->boolean('is_header')->default(false);
            $table->boolean('comments_enabled')->default(true);

            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedBigInteger('views_count')->default(0);

            $table->softDeletes();
            $table->timestamps();

            // slug فريد لكل لغة (ADR slug per-locale, يشمل المحذوف منطقياً)
            $table->unique(['locale', 'slug']);

            // فهارس مُوجَّهة لأنماط الاستعلام (ADR §3 index design)
            $table->index(['status', 'locale', 'published_at'], 'articles_status_locale_pub_idx');
            $table->index(['primary_category_id', 'status', 'published_at'], 'articles_pcat_status_pub_idx');
            $table->index(['deleted_at', 'status', 'published_at'], 'articles_deleted_status_pub_idx');
            $table->index(['type', 'status', 'published_at'], 'articles_type_status_pub_idx');
            $table->index(['is_featured', 'published_at'], 'articles_featured_pub_idx');
        });

        // ─── 2. article_category (التصنيفات الثانوية — ADR A3.2 ≤3) ──────
        Schema::create('article_category', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['article_id', 'category_id']);
            $table->index(['category_id', 'article_id']);
        });

        // ─── 3. article_revisions (لقطات غير قابلة للتعديل) ─────────────
        Schema::create('article_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('editor_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('title', 200);
            $table->text('excerpt')->nullable();
            $table->longText('content');
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords', 255)->nullable();
            $table->string('status_snapshot', 20);
            $table->json('flags_snapshot')->nullable();
            $table->json('tags_snapshot')->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['article_id', 'created_at']);
        });

        // ─── 4. article_url_history (ADR A4 — التقاط فقط، resolver لاحقاً) ─
        Schema::create('article_url_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('locale', 10);
            $table->string('old_path', 255);
            $table->string('reason', 50)->nullable();
            $table->timestamp('created_at')->nullable()->index();

            $table->unique(['locale', 'old_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_url_history');
        Schema::dropIfExists('article_revisions');
        Schema::dropIfExists('article_category');
        Schema::dropIfExists('articles');
    }
};
