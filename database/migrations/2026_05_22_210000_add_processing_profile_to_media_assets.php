<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تلميح معالجة محايد للمحتوى يوجّه مخرجات خط الترميز القائم:
 *   processing_profile : null (افتراضي — HLS + poster) | reel (+ MP4 renditions + WebP thumbnail)
 *
 * ليس بنية وسائط موازية — مجرّد بارامتر داخل نفس TranscodeVideoAssetJob/VideoTranscoder.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('processing_profile', 20)->nullable()->after('processing_status');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropColumn('processing_profile');
        });
    }
};
