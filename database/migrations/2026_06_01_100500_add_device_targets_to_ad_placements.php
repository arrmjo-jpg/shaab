<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أهليّة الجهاز على مستوى الإسناد (placement) — تفصل الأهليّة عن التدوير: نفس الإبداع
 * قد يكون مؤهّلاً لأجهزة مختلفة في مساحات مختلفة. null = كل الأجهزة؛ وإلا قائمة فئات
 * (desktop/mobile/tablet). تُجزّئ بِركة المرشّحين بـ (zone, locale, device).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_placements', function (Blueprint $table): void {
            $table->json('device_targets')->nullable()->after('weight');
        });
    }

    public function down(): void
    {
        Schema::table('ad_placements', function (Blueprint $table): void {
            $table->dropColumn('device_targets');
        });
    }
};
