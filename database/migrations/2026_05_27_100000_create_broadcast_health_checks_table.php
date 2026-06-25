<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مراقبة صحّة البثّ (B3) — سجلّ تاريخ الفحوصات + عدّاد الإخفاقات المتتالية (قاطع
 * الدائرة/anti-flap). اللقطة (last_health_*) موجودة على broadcasts منذ B1.2؛ هذا
 * التاريخ يُقلَّم بنافذة احتجاز لتفادي النمو غير المحدود.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_health_checks', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('broadcast_id')->constrained('broadcasts')->cascadeOnDelete();
            $table->string('status', 20);              // healthy|failed
            $table->unsignedInteger('latency_ms')->nullable();
            $table->string('failure_reason', 255)->nullable();
            $table->timestamp('checked_at')->index();
            // لا timestamps — checked_at هو زمن الحدث (سجلّ خفيف).

            $table->index(['broadcast_id', 'checked_at'], 'bhc_broadcast_checked_idx');
        });

        Schema::table('broadcasts', function (Blueprint $table): void {
            // عدّاد الإخفاقات المتتالية لقاطع الدائرة (لا يُفشَّل إلا بعد عتبة).
            $table->unsignedSmallInteger('health_consecutive_failures')->default(0)->after('last_health_message');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropColumn('health_consecutive_failures');
        });
        Schema::dropIfExists('broadcast_health_checks');
    }
};
