<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إسناد الإبداع إلى مساحة (creative ↔ zone) — هو «المرشّح» القابل للعرض. إبداع
 * واحد قد يُعرَض في عدّة مساحات بأوزان مختلفة. restrictOnDelete على المساحة يمنع
 * حذف مساحة لها إسنادات (سلامة مرجعية — يُفصَل أولاً). الفهرس المركّب
 * (ad_zone_id, is_active, weight) هو مسار بناء بِركة المرشّحين (Batch 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ad_creative_id')->constrained('ad_creatives')->cascadeOnDelete();
            $table->foreignId('ad_zone_id')->constrained('ad_zones')->restrictOnDelete();

            // null ⇒ يَرِث وزن الإبداع؛ وإلا تجاوز على مستوى الإسناد.
            $table->unsignedInteger('weight')->nullable();
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['ad_creative_id', 'ad_zone_id'], 'ad_placements_creative_zone_unique');
            // مسار الخدمة: مرشّحو مساحة نشِطون مرتّبون بالوزن.
            $table->index(['ad_zone_id', 'is_active', 'weight'], 'ad_placements_serving_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_placements');
    }
};
