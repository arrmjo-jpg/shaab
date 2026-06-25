<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بنية إشعارات البثّ (B8) — لا تستضيف المنصّة بنية إشعارات/تفضيلات/رموز أجهزة قائمة،
 * فنبنيها أصلية. التسليم بنموذج «مواضيع» (FCM topics): الخادم ينشر رسالة واحدة لموضوع
 * فيتولّى FCM التوزيع على المشتركين — لا حلقة على 100k مستخدم. هذا الجدول مصدر الحقيقة
 * للتفضيلات (للأهليّة + الجدولة + حالة الاشتراك):
 *
 *   broadcast_id = NULL  → اشتراك عام: «أعلِمني بالبثوث المباشرة».
 *   broadcast_id != NULL → تذكير حدثٍ بعينه: «ذكّرني بهذا البثّ».
 *
 * إزالة التكرار: فريد (user_id, broadcast_id) للحدث؛ والعام (NULL) عبر firstOrCreate
 * على مستوى التطبيق (NULL مميَّز في فهارس SQLite/MySQL الفريدة).
 *
 * علامتان على broadcasts: live_notified_at (مانع ارتعاش — إشعار مباشر مرّة واحدة) و
 * reminder_dispatched_at (منع تكرار التذكير؛ يُصفَّر عند تغيّر موعد الجدولة).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcast_notification_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('broadcast_id')->nullable()->constrained('broadcasts')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'broadcast_id']);
            $table->index('broadcast_id'); // بحث أمر التذكير (مشتركو حدثٍ بعينه)
        });

        Schema::table('broadcasts', function (Blueprint $table): void {
            // مانع ارتعاش الإشعار المباشر: يُضبط ذرّياً عند أوّل دخول مباشر (إشعار واحد).
            $table->timestamp('live_notified_at')->nullable()->after('last_health_message');
            // منع تكرار التذكير المجدوَل (يُصفَّر عند تغيّر scheduled_at لإعادة الإرسال).
            $table->timestamp('reminder_dispatched_at')->nullable()->after('live_notified_at');
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropColumn(['live_notified_at', 'reminder_dispatched_at']);
        });

        Schema::dropIfExists('broadcast_notification_subscriptions');
    }
};
