<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تعديل معماري مُصرَّح به لـ C1: إضافة نطاق التصنيف (news|opinion|both).
 * إضافي + backfill = both (غير هدّام). التوافق يُفرَض في الـ Action/Guard.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->string('scope', 20)->default('both')->after('locale');
        });

        DB::table('categories')->update(['scope' => 'both']);

        Schema::table('categories', function (Blueprint $table): void {
            $table->index(['scope', 'locale', 'status'], 'categories_scope_locale_status_idx');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_scope_locale_status_idx');
            $table->dropColumn('scope');
        });
    }
};
