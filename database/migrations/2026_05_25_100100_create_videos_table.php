<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مكتبة الفيديو — نوع محتوى من الدرجة الأولى (طويل/مفهرس، مع تصنيفات وقوائم تشغيل).
 *
 * يعيد استخدام بنية AlphaCMS: الفيديو (مرفوع أو خارجي) أصلٌ واحد في media_assets
 * عبر media_asset_id (المرفوع يمرّ بخطّ HLS، والخارجي kind=external عبر
 * ExternalVideoResolver). source_type مُزال-التطبيع (denormalized) من الأصل لتسريع
 * الترشيح. تصنيف واحد لكل فيديو (video_category_id). SEO أعمدة أصلية، تفاعل موحّد
 * (views_count مُزال-التطبيع)، بحث Scout (ResilientSearchable).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('videos', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()
                ->constrained('users')->nullOnDelete();

            // الفيديو = أصل واحد من media_assets (مرفوع أو خارجي) — يُربط في المرحلة 3
            $table->foreignId('media_asset_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();

            // تصنيف واحد لكل فيديو (مُعتمَد) — مستقل عن تصنيفات الأخبار
            $table->foreignId('video_category_id')->nullable()
                ->constrained('video_categories')->nullOnDelete();

            // نوع المصدر مُزال-التطبيع للترشيح السريع: uploaded|youtube|vimeo|direct_mp4
            $table->string('source_type', 20)->default('uploaded')->index();

            $table->string('status', 20)->default('draft')->index();        // draft|scheduled|published|archived
            $table->string('visibility', 20)->default('public')->index();   // public|unlisted|private
            $table->boolean('is_featured')->default(false)->index();

            $table->string('locale', 10)->index();
            $table->uuid('translation_group')->nullable()->index();

            $table->string('title', 200);
            $table->string('slug', 190);
            $table->text('description')->nullable();
            $table->string('excerpt', 500)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();

            // تفاعل مُزال-التطبيع (المصدر الموحّد engagements عبر HasEngagement)
            $table->unsignedBigInteger('views_count')->default(0);

            // SEO أصلي
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords', 255)->nullable();
            $table->string('canonical_url', 255)->nullable();
            $table->string('robots', 50)->nullable();

            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable()->index();

            $table->softDeletes();
            $table->timestamps();

            $table->unique(['locale', 'slug']);
            $table->index(['status', 'visibility', 'locale', 'published_at'], 'videos_status_vis_locale_pub_idx');
            $table->index(['video_category_id', 'status'], 'videos_category_status_idx');
            $table->index(['is_featured', 'status', 'published_at'], 'videos_featured_status_pub_idx');
            $table->index(['deleted_at', 'status', 'published_at'], 'videos_deleted_status_pub_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('videos');
    }
};
