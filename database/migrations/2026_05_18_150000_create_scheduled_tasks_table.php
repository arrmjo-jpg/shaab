<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_tasks', function (Blueprint $table): void {
            // مفتاح ثابت من سجل الكود (SchedulerRegistry) — لا مهام يعرّفها المستخدم
            $table->string('key')->unique();
            $table->boolean('enabled')->default(true);
            $table->text('notes')->nullable();
            // بيانات تشغيل (تُحدَّث من خطّافات المُجدوِل / التشغيل اليدوي)
            $table->timestamp('last_run_at')->nullable();
            $table->string('last_status')->default('never'); // never|running|success|failed
            $table->unsignedInteger('last_runtime_ms')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_tasks');
    }
};
