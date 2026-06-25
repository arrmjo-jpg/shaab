<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * علم «مميَّز» للريل — مصدر الحقيقة لنقطة النهاية العامة /reels/featured.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reels', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('reels', function (Blueprint $table): void {
            $table->dropColumn('is_featured');
        });
    }
};
