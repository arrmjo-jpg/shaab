<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الحملات الإعلانية — حاوية الجدولة/الأولوية/الوزن/الميزانية. الحملة تملك إبداعات
 * (ad_creatives). حقول الميزانية/الوتيرة/الاستهداف مُخزَّنة كـ«جاهزة-مستقبلاً»
 * (budget/pacing/targeting-ready) دون محرّك في هذه المرحلة.
 *
 * النافذة الزمنية (starts_at/ends_at) + الحالة (status) تحكمان الأهلية للعرض؛
 * استعلام التجميع يرشّح now() دائماً (المصدر الصحيح حتى مع كاش المرشّحين).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name', 150);
            $table->string('advertiser_name', 150)->nullable();

            $table->string('status', 20)->default('draft')->index();
            $table->unsignedInteger('priority')->default(0)->index();
            $table->unsignedInteger('weight')->default(1);

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // ── جاهز-مستقبلاً (لا محرّك الآن) ──
            $table->decimal('budget_total', 12, 2)->nullable();
            $table->decimal('budget_spent', 12, 2)->default(0);
            $table->string('pacing_mode', 20)->default('none');
            $table->json('targeting')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            // مسار الأهلية للعرض: حالة + نافذة زمنية.
            $table->index(['status', 'starts_at', 'ends_at'], 'ad_campaigns_status_window_idx');
            $table->index(['status', 'priority'], 'ad_campaigns_status_priority_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_campaigns');
    }
};
