<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول pivot لربط المقالات بأصول المكتبة المركزية (P9.2 — B.2a).
 *
 * كل صف يمثّل "إسناد أصل إلى مقال ضمن مجموعة":
 *   cover   → الغلاف (تطبيق واحد فقط — يُفرَض بالحذف قبل الإضافة)
 *   gallery → معرض الصور (متعدّد، مرتَّب بـ position)
 *   inline  → صور داخل نصّ المحرّر (مرتَّبة)
 *   video   → مقاطع الفيديو (مرتَّبة)
 *
 * الأصل المكتبي (MediaAsset) مُشترَك بين مقالات متعدّدة — حذف الصف هنا
 * لا يحذف الأصل من المكتبة المركزية.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_media', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('article_id')
                ->constrained('articles')
                ->cascadeOnDelete();

            $table->foreignId('media_asset_id')
                ->constrained('media_assets')
                ->cascadeOnDelete();

            // cover | gallery | inline | video
            $table->string('collection', 20);

            // ترتيب ضمن المجموعة (gallery/inline/video) — 0 = أوّل
            $table->unsignedSmallInteger('position')->default(0);

            $table->timestamps();

            // نفس الأصل لا يُسنَد إلى نفس المقال+المجموعة مرّتين
            $table->unique(['article_id', 'collection', 'media_asset_id'], 'art_media_unique');

            // فهرس القراءة: مقال+مجموعة+ترتيب
            $table->index(['article_id', 'collection', 'position'], 'art_media_coll_pos_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_media');
    }
};
