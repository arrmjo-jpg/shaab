<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول إشعارات Laravel القياسي (قناة database للـ Notifiable).
 *
 * متعدّد الأشكال عبر notifiable_type/notifiable_id (morphs يُنشئ الفهرس) فلا
 * مفاتيح أجنبية — والترتيب غير حرج. data نصّ JSON (حمولة الإشعار)، read_at
 * يُحدَّد عند القراءة. هذا هو نظام الإشعارات الوحيد للكاتب (P1.2) — لا يتقاطع مع
 * broadcast_notification_subscriptions (تفضيلات بثّ FCM، نظام منفصل).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
