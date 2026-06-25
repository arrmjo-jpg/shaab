<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تاريخ مسارات العدد لتحويل الروابط القديمة عند تغيّر الـ slug (مرآة
 * ArticleUrlHistory) — يحافظ على SEO/الروابط العميقة. فريد لكل (locale, old_path).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epaper_url_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('epaper_id')->constrained('epapers')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('old_path');
            $table->string('reason')->default('slug_change');
            $table->timestamps();

            $table->unique(['locale', 'old_path']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epaper_url_history');
    }
};
