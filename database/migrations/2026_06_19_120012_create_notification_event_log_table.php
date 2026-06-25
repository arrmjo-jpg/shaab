<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ الأحداث الواردة (تدقيق + تجميع digest + إعادة تشغيل). decision = قرار المُنسّق
 * (campaign|direct|ignore)، وcampaign_id يربط الحملة الناتجة إن وُجدت.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_event_log', function (Blueprint $table): void {
            $table->id();
            $table->string('event_key', 80);
            $table->string('source', 20); // domain|scheduled|manual|system
            $table->string('fingerprint', 64)->nullable()->index(); // بصمة محتوى — observability/كشف تكرار (لا تمنع تنفيذاً)
            $table->json('payload')->nullable();
            $table->string('decision', 15)->nullable(); // campaign|direct|ignore
            $table->foreignId('campaign_id')->nullable()->constrained('notification_campaigns')->nullOnDelete();
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();
            $table->index(['event_key', 'occurred_at'], 'nelog_event_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_log');
    }
};
