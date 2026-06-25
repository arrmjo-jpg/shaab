<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * دورة حياة معالجة الفيديو المرفوع (P9 — Wave 3).
 *
 *   processing_status : null (صور/خارجي) | queued | processing | ready | failed
 *   duration_seconds  : مدّة الفيديو (من ffprobe)
 *
 * مخرجات HLS (master + variants) والـ poster تُخزَّن في عمود conversions:
 *   conversions.hls    = { master: path, variants: { '720p': path, ... } }
 *   conversions.poster = { path, width, height }
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('processing_status', 20)->nullable()->after('kind')->index();
            $table->unsignedInteger('duration_seconds')->nullable()->after('height');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['processing_status']);
            $table->dropColumn(['processing_status', 'duration_seconds']);
        });
    }
};
