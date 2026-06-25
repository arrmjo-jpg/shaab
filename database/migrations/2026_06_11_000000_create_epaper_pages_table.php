<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * نصّ صفحات العدد المُستخرَج (Phase 4a — OCR). صفّ لكل صفحة من الوثيقة الحاليّة،
 * يُعاد بناؤه عند كل استخراج (idempotent: حذف ثمّ إدراج). يُحذف مع العدد (cascade).
 * النصّ المخزَّن هنا أساس بحث «داخل العدد» في Phase 4b — لا واجهة/بحث هنا.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epaper_pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('epaper_id')->constrained('epapers')->cascadeOnDelete();
            $table->unsignedInteger('page_number');
            $table->longText('text')->nullable();
            // مصدر النصّ لهذه الصفحة: embedded | google_document_ai.
            $table->string('source', 30)->nullable();
            // علامة سريعة لوجود نصّ (تغذّي إحصاء التغطية + بحث Phase 4b).
            $table->boolean('has_text')->default(false);
            $table->timestamps();

            $table->unique(['epaper_id', 'page_number']);
            $table->index(['epaper_id', 'has_text']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epaper_pages');
    }
};
