<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * أعضاء الفريق — صفحات تعريفية بفريق العمل (مصوّرون/مبرمجون/مهندسون...). محتوى
 * تعريفيّ بحت، مستقلّ تماماً عن جدول users (ليسوا مستخدمي نظام، لا دخول للوحة).
 * نطاق عربيّ أحادي (لا locale prefix في الروابط): canonical = /team/{slug}.
 *
 * يعيد استخدام نمط AlphaCMS: uuid عام، أعمدة SEO أصلية، slug عربيّ فريد، حالة
 * نشاط بسيطة (active/inactive)، ترتيب يدوي، وتدقيق موحّد عبر AuditsChanges.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_members', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('name', 150);
            $table->string('job_title', 150);
            $table->string('department', 100)->nullable()->index();

            // slug عربيّ فريد عالمياً (نطاق أحادي اللغة — لا فرادة per-locale)
            $table->string('slug', 190)->unique();

            $table->longText('bio')->nullable(); // HTML مُنقّى (PageContentSanitizer)
            // الصورة عبر المكتبة المركزية (MediaAsset) — توحيد CDN/cache/conversions
            // والحوكمة. nullOnDelete: حذف الأصل يفصل الرابط ويُبقي العضو.
            $table->foreignId('avatar_asset_id')->nullable()
                ->constrained('media_assets')->nullOnDelete();
            $table->json('social_links')->nullable(); // يغذّي sameAs في Person JSON-LD (Slice 4)

            // SEO أصلي (أعمدة هي مصدر الحقيقة — نفس نمط المقالات/الصفحات)
            $table->string('seo_title', 255)->nullable();
            $table->text('seo_description')->nullable();
            $table->string('seo_keywords', 255)->nullable();
            $table->string('canonical_url', 255)->nullable();
            $table->string('robots', 50)->nullable();

            $table->string('status', 20)->default('active')->index();
            $table->unsignedInteger('sort_order')->default(0)->index();

            $table->softDeletes();
            $table->timestamps();

            // العرض العام: نشِط + مرتّب
            $table->index(['status', 'sort_order'], 'team_members_status_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};
