<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * المساحات الإعلانية (Ad Zones) — مواضع عرض مستقرّة تُعرّف بمفتاح برمجيّ ثابت
 * (key) تستهلكه الواجهات عبر GET /api/v1/ads/serve/{key}. تُفصَل عن الإبداعات
 * (الحملات/الإبداعات) وتُربط بها عبر ad_placements. soft delete للاسترجاع/السلّة؛
 * المفتاح فريد ويُحلّ مرّة لكاش طويل في طبقة الخدمة (Batch 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_zones', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name', 150);
            $table->string('description', 255)->nullable();

            // نوع المساحة + استراتيجية الاختيار (تُحوَّل لتعداد PHP في الموديل).
            $table->string('placement_type', 30)->default('banner')->index();
            $table->string('selector_strategy', 20)->default('weighted');

            // أبعاد اختيارية (إرشاد العرض/التحقّق من الإبداع).
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();

            // null = كل اللغات؛ وإلا مساحة خاصّة بلغة.
            $table->string('locale', 10)->nullable()->index();

            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);

            // لا soft delete: المساحة كيان إعداد — تعطيل عبر is_active + حذف صلب
            // محميّ بـ restrictOnDelete على ad_placements (لا حذف ولها إسنادات).
            $table->timestamps();

            $table->index(['is_active', 'placement_type'], 'ad_zones_active_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_zones');
    }
};
