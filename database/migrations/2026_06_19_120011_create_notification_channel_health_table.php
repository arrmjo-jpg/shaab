<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * السجلّ المركزيّ لصحّة القنوات — صفّ لكلّ قناة، يُحدَّث بـprobe مجدول + تغذية حيّة من فشل
 * الإرسال (consecutive_failures). تقرؤه المعاينة والحملات قبل الإرسال (بوّابتا التوفّر).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_channel_health', function (Blueprint $table): void {
            $table->id();
            $table->string('channel', 20)->unique();
            $table->string('effective_state', 20)->default('unconfigured'); // محسوبة: healthy|degraded|disabled|unconfigured
            $table->boolean('configured')->default(false);
            $table->boolean('healthy')->default(false);
            $table->timestamp('last_checked_at')->nullable();
            $table->timestamp('last_ok_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_channel_health');
    }
};
