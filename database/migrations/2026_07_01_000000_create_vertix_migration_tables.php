<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * جدول حالة ترحيل Vertix فقط (لا جدول mapping ولا source_id إطلاقاً).
 *
 * الهوية محفوظة كما هي: articles.id = art_news.newsid و categories.id = art_categories.catid.
 * المطابقة/الدّيدوب يتمّان مباشرةً على المفتاح الأساسيّ للجدول الهدف (وجود id = مُستورَد).
 *
 * vertix_runs = حالة كلّ مرحلة (categories|news): عدّادات + مؤشّرا الترتيب التنازليّ
 * (high_water للجديد، cursor للردم) + سجلّ أخطاء مُقتضب. ليست طبقة تحويل معرّفات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vertix_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('phase', 20);   // categories | news
            $table->string('status', 20)->default('idle'); // idle|running|completed|failed
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('imported')->default(0);
            $table->unsignedInteger('failed')->default(0);
            // ترتيب الأحدث ← الأقدم: high_water = أعلى id مُستورَد (يلتقط الجديد)؛
            // cursor = أرضيّة الردم التنازليّ؛ backfill_done = اكتمل الردم التاريخيّ.
            $table->unsignedBigInteger('high_water')->default(0);
            $table->unsignedBigInteger('cursor')->default(0);
            $table->boolean('backfill_done')->default(false);
            // سجلّ أخطاء مُقتضب (آخر ~50): [{type, id, error, at}] — للعرض فقط، لا مطابقة.
            $table->json('errors')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique('phase');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vertix_runs');
    }
};
