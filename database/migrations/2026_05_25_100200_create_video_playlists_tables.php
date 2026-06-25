<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قوائم تشغيل الفيديو (من الدرجة الأولى) + جدول الربط المرتّب playlist_video.
 * قوائم منسَّقة يدوياً (curated) مع ترتيب صريح (position) قابل للسحب. SEO أصلي،
 * غلاف اختياري من مكتبة الوسائط، رؤية ودورة حياة مماثلتان للفيديو.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_playlists', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('locale', 10)->index();
            $table->uuid('translation_group')->nullable()->index();

            $table->string('title', 200);
            $table->string('slug', 190);
            $table->text('description')->nullable();

            $table->foreignId('cover_media_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();

            $table->string('status', 20)->default('draft')->index();      // draft|scheduled|published|archived
            $table->string('visibility', 20)->default('public')->index(); // public|unlisted|private
            $table->boolean('is_featured')->default(false)->index();

            $table->unsignedInteger('sort_order')->default(0);

            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords', 255)->nullable();
            $table->string('canonical_url', 255)->nullable();
            $table->string('robots', 50)->nullable();

            $table->timestamp('published_at')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['locale', 'slug']);
            $table->index(['status', 'visibility', 'locale', 'published_at'], 'vplaylists_status_vis_locale_pub_idx');
            $table->index(['is_featured', 'status', 'published_at'], 'vplaylists_featured_status_pub_idx');
        });

        // جدول الربط المرتّب — فيديو ضمن قائمة بترتيب صريح (سحب لإعادة الترتيب).
        Schema::create('playlist_video', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_playlist_id')->constrained('video_playlists')->cascadeOnDelete();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['video_playlist_id', 'video_id']);
            $table->index(['video_playlist_id', 'position'], 'playlist_video_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_video');
        Schema::dropIfExists('video_playlists');
    }
};
