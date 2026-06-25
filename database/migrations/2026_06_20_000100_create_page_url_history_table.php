<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ المسارات القانونية القديمة للصفحات الثابتة — يلتقط canonical السابق عند
 * تغيّر slug/locale لصفحة سبق نشرها، فيُمكِّن إعادة توجيه 301 (حفظ قيمة SEO ومنع
 * كسر الروابط/المُشارَكات). مرآة reel_url_history / article_url_history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_url_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('page_id')->constrained('pages')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('old_path');
            $table->string('reason', 40)->nullable();
            $table->timestamps();

            // فرادة قاعديّة على (locale, old_path) — تمنع التكرار وتدعم firstOrCreate
            // كحارس نهائي بجانب الالتقاط الشرطي في UpdatePageAction.
            $table->unique(['locale', 'old_path']);
            $table->index('page_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_url_history');
    }
};
