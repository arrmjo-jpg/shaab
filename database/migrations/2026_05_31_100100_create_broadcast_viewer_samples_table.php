<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عيّنات الحضور المتزامن للبثّ (تيليمتري إلى-الأمام فقط) — يكتبها مُزامن العدّاد
 * الدوريّ (SyncBroadcastViewerCountsAction, everyMinute) من محرّك الحضور (Redis).
 * يمكّن «الذروة/المتوسّط/منحنى التزامن» بصدق — الحضور نفسه يبقى تقريبياً (B5). نافذة
 * متدحرجة (تقليم حسب broadcast.analytics.sample_retention_days) لحدّ النمو على القنوات
 * الدائمة. ذروة كلّ الأزمنة تُحفَظ دائماً في broadcasts.peak_viewer_count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_viewer_samples', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->unsignedInteger('viewers')->default(0);
            $table->timestamp('sampled_at');

            $table->index(['broadcast_id', 'sampled_at'], 'bvs_broadcast_sampled_idx');
        });

        Schema::table('broadcasts', function (Blueprint $table): void {
            // ذروة الحضور المتزامن (كلّ الأزمنة) — دائمة، تتجاوز نافذة العيّنات المتدحرجة.
            $table->unsignedInteger('peak_viewer_count')->default(0)->after('viewer_count');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropColumn('peak_viewer_count');
        });

        Schema::dropIfExists('broadcast_viewer_samples');
    }
};
