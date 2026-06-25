<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الإبداعات الإعلانية — الوحدة المعروضة فعلاً. تتبع حملة واحدة. ثلاثة أنواع:
 *   - image : أصل في media_assets (media_asset_id) — مرآة نمط Video (لا Spatie-على-الموديل).
 *   - html  : html_code مُنقّى (HTMLPurifier) يُعرَض في iframe معزول.
 *   - video : جاهز-مستقبلاً فقط.
 *
 * landing_url يُتحقَّق (http/https) عند الكتابة ويُعاد التحقّق عند تحويل النقرة (Batch 3/4).
 * weight وزن صريح للتدوير (ليس بالنقرات).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_creatives', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->foreignId('ad_campaign_id')->constrained('ad_campaigns')->cascadeOnDelete();

            $table->string('type', 20)->default('image')->index();
            $table->string('title', 200);
            $table->string('alt_text', 255)->nullable();
            $table->string('landing_url', 500)->nullable();
            $table->longText('html_code')->nullable();

            // صورة/فيديو الإبداع = أصل مركزيّ واحد (مرآة Video::media_asset_id).
            $table->foreignId('media_asset_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();

            $table->unsignedInteger('weight')->default(1);
            $table->boolean('is_active')->default(true)->index();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['ad_campaign_id', 'is_active'], 'ad_creatives_campaign_active_idx');
            $table->index(['type', 'is_active'], 'ad_creatives_type_active_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_creatives');
    }
};
