<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تجميع يوميّ (event × channel × يوم) — للوحات الاتّجاه، يُملأ من عدّادات القنوات عبر مهمّة
 * دوريّة (لا COUNT على ملايين صفوف deliveries).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_stats_daily', function (Blueprint $table): void {
            $table->id();
            $table->date('date');
            $table->string('event_key', 80);
            $table->string('channel', 20);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('opened')->default(0);
            $table->unsignedInteger('clicked')->default(0);
            $table->timestamps();
            $table->unique(['date', 'event_key', 'channel'], 'nsd_date_event_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_stats_daily');
    }
};
