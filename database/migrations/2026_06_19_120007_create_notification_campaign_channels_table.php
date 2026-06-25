<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تنفيذ الحملة لكلّ قناة + عدّاداتها المُجمّعة (المصدر الموثوق للإحصاء — لا COUNT على
 * deliveries). tracking_mode = aggregate (قنوات topic) أو per_recipient (deliveries).
 * status يشمل skipped/superseded (لا يُسقطان الحملة). fallback_from بنية v1.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaign_channels', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('notification_campaigns')->cascadeOnDelete();
            $table->string('channel', 20); // firebase|whatsapp|email
            $table->string('mode', 20)->default('automatic'); // automatic|manual_approval
            $table->string('tracking_mode', 15)->default('aggregate'); // aggregate|per_recipient
            $table->string('status', 15)->default('pending'); // pending|skipped|superseded|sending|completed|failed
            $table->string('skip_reason', 255)->nullable();
            $table->string('addressing', 15)->default('per_recipient'); // per_recipient|topic
            $table->unsignedInteger('channel_priority')->default(100); // snapshot من المصفوفة — حملة immutable (لا قراءة حيّة)
            $table->string('fallback_channel', 20)->nullable(); // snapshot هدف التحويل (تنفيذ v1.2)
            $table->unsignedBigInteger('template_id')->nullable(); // snapshot القالب المُستخدَم (بلا FK — يصمد لو حُذف القالب)
            $table->json('content')->nullable(); // الرسالة المُصيَّرة (render مرّة واحدة عند الإنشاء — immutable)
            $table->string('fallback_from', 20)->nullable(); // بنية v1.1 — تنفيذ التحويل مؤجّل
            $table->string('topic', 120)->nullable();
            $table->string('provider_ref', 120)->nullable();
            $table->unsignedInteger('targeted')->default(0);
            $table->unsignedInteger('sent')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->unsignedInteger('failed')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->unsignedInteger('invalid')->default(0);
            $table->unsignedInteger('opened')->default(0);
            $table->unsignedInteger('clicked')->default(0);
            $table->timestamps();
            $table->unique(['campaign_id', 'channel'], 'ncc_campaign_channel_unique');
            $table->index(['campaign_id', 'status'], 'ncc_campaign_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaign_channels');
    }
};
