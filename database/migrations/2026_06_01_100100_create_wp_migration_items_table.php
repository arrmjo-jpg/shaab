<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_migration_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('run_id')->constrained('wp_migration_runs')->cascadeOnDelete();
            $table->unsignedBigInteger('wp_post_id');
            $table->foreignId('article_id')->nullable()->constrained('articles')->nullOnDelete();
            $table->string('status', 20)->default('pending');

            // النوع المحسوم للمنشور (news|opinion) — من خرائط التصنيفات.
            $table->string('target_type', 20)->nullable();

            // طوابع نقاط التحقّق لكل خطوة (استئناف دقيق — لا تكرار للعمل المكتمل).
            $table->timestamp('content_imported_at')->nullable();
            $table->timestamp('media_imported_at')->nullable();
            $table->timestamp('seo_imported_at')->nullable();
            $table->timestamp('redirects_created_at')->nullable();

            $table->unsignedInteger('attempts')->default(0);
            $table->string('last_step', 50)->nullable();
            $table->text('last_error')->nullable();

            // بصمة المصدر المُطبَّع (كشف تغيّر/تخطّي غير المتغيّر عند إعادة التشغيل).
            $table->string('content_checksum', 64)->nullable();

            // تحذيرات QA (جسم شبه فارغ، وسائط غير محلولة، embed مجهول…) — لا فقدان صامت.
            $table->json('flags')->nullable();

            $table->timestamps();

            $table->unique(['run_id', 'wp_post_id']);
            $table->index(['run_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_migration_items');
    }
};
