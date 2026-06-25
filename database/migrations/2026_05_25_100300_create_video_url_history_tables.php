<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّات المسارات القانونية القديمة لنطاق الفيديو — تلتقط canonical السابق عند
 * تغيّر slug/locale لتمكين إعادة توجيه 301 (حفظ قيمة SEO ومنع كسر الروابط).
 *
 * جدولان من الدرجة الأولى — للفيديو **وللقوائم** معاً (متطلّب أساسي): تغيّر slug
 * قائمة التشغيل يجب أن يُعيد التوجيه 301 تماماً كالمقالات/الريلز/صفحات الفيديو.
 * مرآة reel_url_history / article_url_history.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_url_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_id')->constrained('videos')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('old_path');
            $table->string('reason', 40)->nullable();
            $table->timestamps();

            $table->unique(['locale', 'old_path']);
            $table->index('video_id');
        });

        Schema::create('playlist_url_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('video_playlist_id')->constrained('video_playlists')->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('old_path');
            $table->string('reason', 40)->nullable();
            $table->timestamps();

            $table->unique(['locale', 'old_path']);
            $table->index('video_playlist_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playlist_url_history');
        Schema::dropIfExists('video_url_history');
    }
};
