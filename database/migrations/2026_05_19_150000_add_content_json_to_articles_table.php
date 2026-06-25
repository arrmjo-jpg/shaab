<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P4-D1 مقفول: content_json هو مصدر الحقيقة (TipTap JSON).
 * `content` يصبح ناتج عرض HTML مُعقَّم مشتقّ (nullable، غير قابل للكتابة).
 * إضافي + غير هدّام.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->json('content_json')->nullable()->after('excerpt');
        });

        Schema::table('articles', function (Blueprint $table): void {
            $table->longText('content')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('content_json');
            $table->longText('content')->nullable(false)->change();
        });
    }
};
