<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الحملة — وحدة التنسيق الناتجة من حدث حين تقرّر السياسة Campaign. الحالة الإجماليّة
 * مشتقّة من حالات قنواتها. dedupe_hash يمنع التكرار. لا FK لجداول المحتوى (مفصول —
 * المراجع تُحمَل في القالب/الرابط نصًّا). created_by null ⇒ مصدر نظاميّ/تلقائيّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('event_key', 80)->index();
            $table->string('source', 20)->default('manual'); // domain|scheduled|manual|system
            $table->string('trigger_type', 20)->default('manual'); // automatic|manual|scheduled
            $table->string('priority', 20)->default('normal');
            $table->string('title', 200)->nullable();
            $table->json('content')->nullable(); // رسالة الحملة (title/body/image/deep_link) — مصدر v1 قبل القوالب (Phase 4)
            $table->string('status', 25)->default('draft')->index(); // draft|scheduled|queued|sending|paused|completed|partially_completed|failed|cancelled
            $table->foreignId('audience_id')->nullable()->constrained('notification_audiences')->nullOnDelete();
            $table->json('audience_spec')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('targeted_count')->default(0);
            $table->string('dedupe_hash', 64)->nullable()->unique(); // قيد ذرّيّ: حملة واحدة لكلّ هويّة dedupe (null = بلا dedupe)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_campaigns');
    }
};
