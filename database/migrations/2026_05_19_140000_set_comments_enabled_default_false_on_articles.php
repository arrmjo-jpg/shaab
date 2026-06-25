<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قرار العمل المقفول: التعليقات معطّلة افتراضياً — التفعيل صريح فقط.
 * (تصحيح خطأ Wave C2 الذي ضبط الافتراضي true.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->boolean('comments_enabled')->default(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->boolean('comments_enabled')->default(true)->change();
        });
    }
};
