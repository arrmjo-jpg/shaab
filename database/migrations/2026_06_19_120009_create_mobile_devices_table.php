<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ أجهزة الـpush — يفتح استهداف Android/iOS والضيوف (user_id=null). device_id (UUID
 * لكلّ تثبيت) هو المفتاح الفريد ⇒ upsert عند تدوير fcm_token. التوكنات الميتة تُعطَّل عند الإرسال.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete(); // null = ضيف
            $table->string('device_id', 100)->unique();
            $table->string('platform', 10); // android|ios|web
            $table->string('fcm_token', 255)->index();
            $table->string('locale', 5)->nullable(); // لغة push لكلّ جهاز (منصّة ثنائيّة اللغة)
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index(['platform', 'is_active'], 'mdev_platform_active_idx');
            $table->index(['user_id', 'is_active'], 'mdev_user_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobile_devices');
    }
};
