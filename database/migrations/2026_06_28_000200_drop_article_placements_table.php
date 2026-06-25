<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إسقاط جدول التنسيبات التحريرية (article_placements) — أُلغيت المنظومة بالكامل.
 * مكان عرض الخبر (هيرو/عاجل/هيدر/اخترنالكم) صار مدفوعاً بأعلام جدول الأخبار
 * (is_featured/is_breaking/is_header/is_editor_pick) يضبطها المحرّر من نموذج الخبر.
 *
 * اختياري: تشغيله يحذف بيانات التنسيب القديمة (تكوين تحريري لا محتوى) — لا أثر
 * على المقالات نفسها. down() يعيد بناء بنية الجدول فارغاً (لا يستعيد البيانات).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('article_placements');
    }

    public function down(): void
    {
        if (Schema::hasTable('article_placements')) {
            return;
        }

        Schema::create('article_placements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->string('zone', 20);
            $table->string('locale', 10);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['zone', 'locale', 'article_id'], 'placements_zone_locale_article_uq');
            $table->index(['zone', 'locale', 'position'], 'placements_zone_locale_pos_idx');
            $table->index(['zone', 'locale', 'starts_at', 'ends_at'], 'placements_window_idx');
        });
    }
};
