<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاريخ نسخ العدد (versioning + replace PDF): كل استبدال للـ PDF يُنشئ صفّاً
 * يحفظ الأصل السابق وملاحظة ومَن نفّذ — سجلّ تدقيقيّ غير متلِف (لا فقدان للنسخ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epaper_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('epaper_id')->constrained('epapers')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('note')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['epaper_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epaper_versions');
    }
};
