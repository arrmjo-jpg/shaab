<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مستوى وصول العدد (حالة نطاق، لا إعداد خارجيّ): public | subscriber | private.
 * افتراضيّ public — يحافظ على سلوك الأعداد القائمة. forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epapers', function (Blueprint $table): void {
            $table->string('access_level', 20)->default('public')->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('epapers', function (Blueprint $table): void {
            $table->dropColumn('access_level');
        });
    }
};
