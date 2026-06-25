<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * البثّ — نطاق مستقل (بثّ خارجي موثوق فقط: لا رفع/ترميز/تسجيل/وسيط تشغيل).
 *
 * kind (live|tv|radio) تحريري يقود تقسيم المسارات العامة، منفصل عن source_type
 * التقني. المصدر رابط خارجي موثوق مُتحقَّق (allow-list + SafeUrl). عدّاد المشاهدين
 * لقطة (snapshot) غير مرجعية للزمن الحقيقي. الصورة مسار/رابط (لا رفع في هذا النطاق).
 * SEO الأصلي + ربط VOD الاختياري يُضافان في B4 (هجرة إضافية) حسب الخطة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('broadcasts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();

            $table->string('title', 200);
            $table->string('slug', 190);
            $table->string('excerpt', 500)->nullable();
            $table->text('description')->nullable();

            $table->string('kind', 10)->index();          // live|tv|radio
            $table->string('source_type', 20)->index();   // hls|iptv|youtube_live|external_provider|icecast|shoutcast
            $table->string('source_url', 2048);
            $table->string('status', 20)->default('draft')->index();

            $table->foreignId('category_id')->nullable()
                ->constrained('broadcast_categories')->nullOnDelete();

            // الصورة مسار/رابط خارجي — لا رفع ملفات في نطاق البثّ
            $table->string('thumbnail_path', 2048)->nullable();
            $table->string('poster_path', 2048)->nullable();

            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('ended_at')->nullable();

            // لقطة صحّة آخر فحص (المنطق في B3)
            $table->timestamp('last_health_check_at')->nullable();
            $table->string('last_health_status', 20)->nullable();
            $table->string('last_health_message', 500)->nullable();

            // لقطة (snapshot) — ليست مصدر الحقيقة للزمن الحقيقي (Redis في B5)
            $table->unsignedInteger('viewer_count')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_featured')->default(false)->index();
            $table->boolean('is_public')->default(false)->index();

            $table->json('meta')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->softDeletes();
            $table->timestamps();

            $table->unique('slug');
            // أنماط الاستعلام السائدة: مركز العمليات/القوائم العامة حسب النوع+الحالة+الوقت
            $table->index(['kind', 'status', 'scheduled_at'], 'broadcasts_kind_status_sched_idx');
            $table->index(['is_public', 'status', 'kind'], 'broadcasts_public_status_kind_idx');
            $table->index(['deleted_at', 'status'], 'broadcasts_deleted_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('broadcasts');
    }
};
