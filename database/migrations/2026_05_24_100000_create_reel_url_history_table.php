<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ المسارات القانونية القديمة للريلز — يلتقط canonical السابق عند تغيّر
 * slug/locale ليُمكِّن إعادة توجيه 301 (حفظ قيمة SEO ومنع كسر الروابط المُشارَكة).
 * مرآة article_url_history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reel_url_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('reel_id')->constrained('reels')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('old_path');
            $table->string('reason', 40)->nullable();
            $table->timestamps();

            $table->unique(['locale', 'old_path']);
            $table->index('reel_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reel_url_history');
    }
};
