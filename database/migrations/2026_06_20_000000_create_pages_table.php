<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * الصفحات الثابتة (من نحن/الخصوصية/الاستخدام/الشروط/أعلن معنا...) — نوع محتوى
 * من الدرجة الأولى يُدار من لوحة الإدارة (بدل client.json). يعيد استخدام نمط
 * AlphaCMS: هوية موحّدة (users)، أعمدة SEO أصلية، دورة حياة draft→published→archived،
 * أعلام ظهور في الهيدر/التذييل + ترتيب. مستقلّ تماماً (لا تصنيفات ولا وسائط).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            // هوية موحّدة (جدول users) — تبقى عند حذف المستخدم
            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('published_by_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->string('status', 20)->default('draft')->index();
            $table->string('locale', 10)->index();
            $table->uuid('translation_group')->nullable()->index();

            $table->string('title', 200);
            $table->string('slug', 190);
            $table->longText('content')->nullable(); // HTML مُنقّى (PageContentSanitizer)

            // SEO أصلي (أعمدة هي مصدر الحقيقة — نفس نمط المقالات/الريلز)
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords', 255)->nullable();
            $table->string('canonical_url', 255)->nullable();
            $table->string('robots', 50)->nullable();

            // عرض الصفحة (قالب اختياري) + أعلام التنقّل + الترتيب
            $table->string('template', 100)->nullable();
            $table->boolean('show_in_header')->default(false)->index();
            $table->boolean('show_in_footer')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamp('published_at')->nullable()->index();

            $table->softDeletes();
            $table->timestamps();

            // slug فريد لكل لغة (يشمل المحذوف منطقياً عبر الفحص في المولّد)
            $table->unique(['locale', 'slug']);

            // فهارس مُوجَّهة لأنماط الاستعلام (عام: منشور+لغة؛ سلّة)
            $table->index(['status', 'locale', 'published_at'], 'pages_status_locale_pub_idx');
            $table->index(['deleted_at', 'status', 'published_at'], 'pages_deleted_status_pub_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pages');
    }
};
