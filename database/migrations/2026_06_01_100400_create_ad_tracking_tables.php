<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جداول قياس الإعلانات — محوريّة الإسناد (placement-centric): الإسناد يحمل خصائص أداء
 * مستقلّة (نفس الإبداع قد يظهر في إسنادات متعدّدة). التجميع بـ (placement, channel)؛
 * الحملة/الإبداع/المساحة أبعاد تقارير مشتقّة (مُزالة-التطبيع لتبقى عند حذف الإسناد).
 *
 * بلا مفاتيح خارجية (مرآة engagement_counters/content_daily_stats) — جداول تحليلات
 * إلى-الأمام تُغذّى من خطّ التتبّع غير المتزامن (AdEventBuffer::flush). الزيادة ذرّية.
 *
 *   ad_counters    : عدّاد ساخن مُجمَّع لكل إسناد — انطباعات/نقرات (حيّ).
 *   ad_stats_daily : سلسلة زمنية يومية لكل إسناد + أبعاد مشتقّة + تفصيل قناة الانطباع.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_counters', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ad_placement_id');

            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);

            $table->timestamps();

            $table->unique('ad_placement_id', 'ad_counters_placement_unique');
        });

        Schema::create('ad_stats_daily', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('ad_placement_id');

            // أبعاد تقارير مشتقّة (مُزالة-التطبيع) — تبقى للتاريخ حتى بعد حذف الإسناد.
            $table->unsignedBigInteger('ad_zone_id')->nullable();
            $table->unsignedBigInteger('ad_creative_id')->nullable();
            $table->unsignedBigInteger('ad_campaign_id')->nullable();

            $table->date('day');

            $table->unsignedBigInteger('impressions')->default(0);
            $table->unsignedBigInteger('clicks')->default(0);

            // تفصيل قناة الانطباع (تصنيف خشن عبر TrafficChannel).
            $table->unsignedBigInteger('impressions_direct')->default(0);
            $table->unsignedBigInteger('impressions_internal')->default(0);
            $table->unsignedBigInteger('impressions_search')->default(0);
            $table->unsignedBigInteger('impressions_social')->default(0);
            $table->unsignedBigInteger('impressions_referral')->default(0);

            $table->timestamps();

            $table->unique(['ad_placement_id', 'day'], 'ad_stats_daily_placement_day_unique');
            // مسارات تجميع التقارير المشتقّة.
            $table->index(['ad_campaign_id', 'day'], 'ad_stats_daily_campaign_day_idx');
            $table->index(['ad_creative_id', 'day'], 'ad_stats_daily_creative_day_idx');
            $table->index(['ad_zone_id', 'day'], 'ad_stats_daily_zone_day_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_stats_daily');
        Schema::dropIfExists('ad_counters');
    }
};
