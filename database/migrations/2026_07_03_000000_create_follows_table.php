<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * متابعة الكيانات الرياضيّة (نظام «تابع») — يربط المستخدم بكيان رياضيّ خارجيّ من 365
 * (فريق/بطولة/لاعب/مباراة). الهدف **معرّف 365 خارجيّ** (followable_id) لا مفتاح أجنبيّ
 * محليّ — لذلك لا constrained() عليه. شأن مستقلّ عن تفاعل المحتوى المحليّ (engagements):
 * لا counters ولا polymorphic resolver، فقط علاقة مستخدم↔كيان بفرادة لمنع التكرار.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            // نوع الكيان: team | competition | player | match (FollowableType).
            $table->string('followable_type', 16);
            // معرّف الكيان في 365 (خارجيّ — لا مفتاح أجنبيّ محليّ).
            $table->unsignedBigInteger('followable_id');
            $table->timestamps();

            // متابعة واحدة لكل (مستخدم، كيان) — منع التكرار وفرض idempotency.
            $table->unique(['user_id', 'followable_type', 'followable_id'], 'follows_user_target_unique');
            // قائمة «أتابعهم» للمستخدم (مع تصفية اختياريّة بالنوع).
            $table->index(['user_id', 'followable_type'], 'follows_user_type_idx');
            // لاحقاً (المرحلة 2): جلب متابِعي كيانٍ ما لإرسال إشعار مبارياته.
            $table->index(['followable_type', 'followable_id'], 'follows_target_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follows');
    }
};
