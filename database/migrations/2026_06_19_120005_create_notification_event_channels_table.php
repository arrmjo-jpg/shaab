<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مصفوفة (event × channel) — قلب الإعداد. mode = سلوك القناة لهذا الحدث
 * (automatic|manual_approval|disabled). channel_priority = ترتيب التنفيذ. fallback_channel
 * بنية v1.1 (تنفيذ التحويل مؤجّل v1.2). كلّ خليّة تربط قالباً وجمهوراً افتراضيّاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_event_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->constrained('notification_events')->cascadeOnDelete();
            $table->string('channel', 20); // firebase|whatsapp|email
            $table->string('mode', 20)->default('disabled'); // automatic|manual_approval|disabled
            $table->unsignedInteger('channel_priority')->default(100); // ترتيب التنفيذ (1,2,3…)
            $table->string('fallback_channel', 20)->nullable(); // بنية v1.1 — التحويل مؤجّل v1.2
            $table->foreignId('template_id')->nullable()->constrained('notification_templates')->nullOnDelete();
            $table->foreignId('default_audience_id')->nullable()->constrained('notification_audiences')->nullOnDelete();
            $table->string('priority_override', 20)->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'channel'], 'nec_event_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_channels');
    }
};
