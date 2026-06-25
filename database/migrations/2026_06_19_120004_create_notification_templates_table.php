<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قوالب الإشعار لكلّ (event × channel × locale) — استبدال tokens آمن وقت الإرسال.
 * deep_link_type/value يُرسَلان في حمولة data. is_default = القالب الاحتياطيّ للحدث/القناة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 80)->index();
            $table->string('channel', 20); // firebase|whatsapp|email
            $table->string('locale', 5)->default('ar');
            $table->string('title', 255)->nullable();
            $table->text('body')->nullable();
            $table->string('image_strategy', 30)->nullable(); // none|content|custom
            $table->string('deep_link_type', 20)->default('none'); // none|article|category|video|reel|broadcast|external
            $table->string('deep_link_value', 255)->nullable();
            $table->json('variables')->nullable();
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['event_key', 'channel', 'locale'], 'ntpl_event_channel_locale_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
