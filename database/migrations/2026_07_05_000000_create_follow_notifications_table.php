<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ منع تكرار إشعارات «تابع» (المرحلة 2) — صفّ لكلّ (مستخدم، إشعار مُرسَل). منفصل عن جدول إشعارات Laravel
 * (`notifications`) الذي يحمل الإشعار المعروض؛ هذا الجدول هو **الدفتر** الذي يضمن ألّا يُشعَر المستخدم مرّتين.
 *
 * منع التكرار per‑user عبر `dedup_key` (بعد تجميع متابعي المباراة/الفريق/البطولة/اللاعب في مستخدمين مميَّزين):
 *   - تذكير ما قبل المباراة: dedup_key = "reminder:{game_id}"  ⇒ تذكير واحد لكلّ مستخدم لكلّ مباراة.
 *   - حدث مباشر (هدف/بطاقة): dedup_key = "event:{event_id}"   ⇒ إشعار واحد لكلّ مستخدم لكلّ حدث.
 * قيد `unique(user_id, dedup_key)` الواحد يخدم النوعين (يُجهّز الكتلة C دون هجرة جديدة) — أنظف من قيدين
 * متعارضين (MySQL بلا فهارس unique جزئيّة). `event_id` يبقى عمودًا (nullable) للأحداث + الاستعلام.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('follow_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('game_id');
            $table->string('kind', 16);                       // reminder | event
            $table->unsignedBigInteger('event_id')->nullable(); // معرّف حدث 365 (للأحداث المباشرة)؛ null للتذكير
            $table->string('dedup_key', 64);                  // reminder:{game} | event:{eventId}
            $table->timestamp('sent_at');
            $table->timestamps();

            // منع التكرار per‑user (يخدم التذكير والأحداث معًا عبر تسمية dedup_key).
            $table->unique(['user_id', 'dedup_key'], 'follow_notifications_user_dedup_unique');
            $table->index('game_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('follow_notifications');
    }
};
