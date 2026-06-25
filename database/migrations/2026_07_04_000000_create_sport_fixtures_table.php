<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مرآة مواعيد 365 محليًّا (نظام إشعارات «تابع» — المرحلة 2). تُزامَن دوريًّا للكيانات المتابَعة فقط
 * (فرق/بطولات/لاعبون→فِرَقهم/مباريات). مصدرها 365 (معرّفات خارجيّة، لا مفاتيح أجنبيّة محليّة).
 * `last_event_id` مؤشّر آخر حدث مُعالَج، و`next_poll_at` كادنس الاستطلاع المتكيّف (الكتلة C).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sport_fixtures', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('game_id')->unique(); // معرّف مباراة 365
            $table->unsignedBigInteger('competition_id');
            $table->unsignedBigInteger('season_num')->nullable(); // مكافئ season_id في 365
            $table->unsignedBigInteger('home_team_id')->nullable();
            $table->string('home_name')->nullable();
            $table->unsignedBigInteger('away_team_id')->nullable();
            $table->string('away_name')->nullable();
            $table->string('status', 16)->default('scheduled'); // scheduled | live | finished
            $table->timestamp('start_at')->nullable();
            $table->unsignedBigInteger('last_event_id')->nullable(); // مؤشّر آخر حدث مُشعَر (الأحداث المباشرة)
            $table->timestamp('next_poll_at')->nullable();           // متى نستطلع هذه المباراة تاليًا
            $table->timestamps();

            // فهارس مُجهَّزة من البداية (طلب صريح) — استعلامات «متابِعو فريق/بطولة» + نافذة التذكير + الاستطلاع.
            $table->index('competition_id');
            $table->index('home_team_id');
            $table->index('away_team_id');
            $table->index('status');
            $table->index('start_at');
            $table->index('next_poll_at'); // اختيار المباريات المستحقّة للاستطلاع (الكتلة C)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sport_fixtures');
    }
};
