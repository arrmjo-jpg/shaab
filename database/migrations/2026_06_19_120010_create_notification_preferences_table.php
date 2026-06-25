<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تفضيلات المستخدم (إلغاء الاشتراك) لكلّ (scope × channel) — تُرشّح مستلمي الحملة والـDirect.
 * scope_type: global|category|event؛ scope_key يحمل التصنيف/الحدث (فارغ للـglobal).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('scope_type', 12); // global|category|event
            $table->string('scope_key', 80)->default('');
            $table->string('channel', 20);
            $table->boolean('opted_in')->default(true);
            $table->timestamps();
            $table->unique(['user_id', 'scope_type', 'scope_key', 'channel'], 'npref_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
    }
};
