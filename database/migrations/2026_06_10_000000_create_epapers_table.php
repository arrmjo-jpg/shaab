<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Epaper Core (المرحلة 1) — العدد الرقمي للجريدة. الوثيقة تبقى PDF (لا خطّ تحويل
 * إلى صور). المرجع للملف عبر media_asset_id (يعيد استخدام مكتبة الوسائط). أعمدة
 * الميتاداتا (page_count/text_layer/ocr_status) موجودة هنا لكن كشفها/الـ OCR في
 * المرحلة 4. النطاق/الوصول (public/subscriber/private) تُضيفه المرحلة 3 (Access).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('epapers', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('locale', 5)->default('ar');
            $table->unsignedInteger('issue_number');

            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->text('summary')->nullable();
            $table->string('slug', 190);
            $table->date('publication_date');

            // دورة الحياة: draft|scheduled|published|archived (EpaperStatus).
            $table->string('status', 20)->default('draft');

            // الملف الحاليّ (آخر نسخة) — يبقى PDF.
            $table->foreignId('media_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();

            // ميتاداتا الوثيقة — تُملأ عند الاستخراج/الكشف (منطق المرحلة 4).
            $table->unsignedInteger('page_count')->nullable();
            $table->string('text_layer', 20)->nullable();   // present|absent|partial
            $table->string('ocr_status', 20)->nullable();   // pending|processing|done|failed

            // عدّاد النسخ (الإصدارات) — يزيد عند استبدال الـ PDF.
            $table->unsignedInteger('current_version')->default(1);

            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()->constrained('users')->nullOnDelete();

            // يخدم الجدولة والنشر معاً (scheduled = مستقبليّ، published = ماضٍ/الآن).
            $table->timestamp('published_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['locale', 'issue_number']);
            $table->unique(['locale', 'slug']);
            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('epapers');
    }
};
