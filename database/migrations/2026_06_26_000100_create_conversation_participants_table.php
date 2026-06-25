<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعضاء المحادثة — يحدّد من يصل إلى محادثة (التحكّم row-level: لا صلاحيات نظام).
 * last_read_at: علامة قراءة لكل عضو لاحتساب غير المقروء (أكفأ من أعلام لكل رسالة).
 * بيانات تشغيلية عالية التحديث (last_read_at) → غير مُدقَّقة عمداً (لا AuditsChanges).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversation_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamps();

            $table->unique(['conversation_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversation_participants');
    }
};
