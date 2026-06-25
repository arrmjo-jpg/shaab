<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسعة media_assets لتصبح مكتبة الأصول التحريرية المركزية (P9.2 — قرار B).
 *
 * إضافات واقع غرفة الأخبار: checksum (إزالة التكرار)، نصّ بديل، تعليق،
 * كريديت/مصوّر، مصدر/إسناد، وبيانات التحويلات (مشتقّات WebP).
 *
 * أصل واحد يُشارَك عبر مقالات متعدّدة بالإسناد (article_media لاحقاً في B.2)،
 * لا نسخ مكرّرة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            // بصمة المحتوى لمنع تخزين الملف نفسه مرّتين (dedupe)
            $table->string('checksum', 64)->nullable()->after('size');
            $table->index('checksum');

            // بيانات وصفية تحريرية
            $table->text('alt')->nullable()->after('checksum');
            $table->text('caption')->nullable()->after('alt');
            $table->string('credit')->nullable()->after('caption');
            $table->string('source')->nullable()->after('credit');

            // مشتقّات WebP المُولّدة: { thumb: {path,width,height}, medium: {...} }
            $table->json('conversions')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['checksum']);
            $table->dropColumn(['checksum', 'alt', 'caption', 'credit', 'source', 'conversions']);
        });
    }
};
