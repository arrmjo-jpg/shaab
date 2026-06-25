<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حالة الحدث المباشر للمقالات من نوع live (scheduled/live/paused/completed).
 * مستقلّة عن حالة النشر؛ nullable لأنها لا تنطبق على news/opinion.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->string('event_status', 20)->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropColumn('event_status');
        });
    }
};
