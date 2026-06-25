<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ تسليم لكلّ مُستلِم — **شرطيّ**: يُنشأ فقط لقنوات tracking_mode=per_recipient
 * (email/whatsapp/sms). قنوات topic (firebase topic/broadcast) لا صفوف لها (عدّادات فقط).
 * unique(channel,recipient) للـidempotency. مُجزَّأ/مُعيَّن عند الضخامة (الإحصاء من العدّادات).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_channel_id')->constrained('notification_campaign_channels')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained('notification_campaigns')->cascadeOnDelete();
            $table->string('channel', 20);
            $table->string('recipient_type', 15); // user|device|contact|email|phone
            $table->string('recipient_id', 100); // لقطة الهويّة (يصمد لو حُذف المُستلِم)
            $table->string('address_snapshot', 255);
            $table->string('status', 12)->default('pending'); // pending|sent|delivered|failed|invalid|skipped
            $table->string('provider_message_id', 120)->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            $table->unique(['campaign_channel_id', 'recipient_id'], 'nd_channel_recipient_unique');
            $table->index(['campaign_id', 'status'], 'nd_campaign_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
    }
};
