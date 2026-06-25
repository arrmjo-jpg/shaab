<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * قواعد الجمهور المخصّص (custom) — يستند إليها Audience من نوع custom فقط. البانِي
 * المرئيّ للقواعد مؤجّل خارج v1.1؛ هذا الجدول يحمل rules(json) للتوسّع لاحقاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_segments', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('name', 150);
            $table->json('rules')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_segments');
    }
};
