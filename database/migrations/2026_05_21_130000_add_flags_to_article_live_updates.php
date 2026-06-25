<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعلام تحريرية على مستوى التحديث الواحد: عاجل + مميَّز (إضافةً للمثبَّت).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('article_live_updates', function (Blueprint $table): void {
            $table->boolean('is_breaking')->default(false)->after('is_pinned');
            $table->boolean('is_featured')->default(false)->after('is_breaking');
        });
    }

    public function down(): void
    {
        Schema::table('article_live_updates', function (Blueprint $table): void {
            $table->dropColumn(['is_breaking', 'is_featured']);
        });
    }
};
