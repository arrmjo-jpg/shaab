<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * النُّسخ تلتقط المصدر القانوني (content_json) — P4-D1.
 * `content` يصبح لقطة عرض مشتقّة اختيارية (nullable). إضافي/غير هدّام.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_revisions', function (Blueprint $table): void {
            $table->json('content_json')->nullable()->after('excerpt');
        });

        Schema::table('article_revisions', function (Blueprint $table): void {
            $table->longText('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('article_revisions', function (Blueprint $table): void {
            $table->dropColumn('content_json');
        });
    }
};
