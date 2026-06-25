<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wp_migration_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150)->nullable();
            $table->string('status', 20)->default('draft')->index();

            // سياسة حسم تعارض النوع — يختارها المُشغِّل في معاينة الأثر قبل التنفيذ
            // (prefer_news|prefer_articles|exclude). null = لم تُحسَم بعد (لا تنفيذ).
            $table->string('conflict_policy', 20)->nullable();

            // اتصال المصدر (قراءة فقط منطقياً) — كلمة المرور تُخزَّن مُعمّاة عبر cast.
            $table->string('db_host', 191)->nullable();
            $table->unsignedSmallInteger('db_port')->default(3306);
            $table->string('db_name', 191)->nullable();
            $table->string('db_username', 191)->nullable();
            $table->text('db_password')->nullable();
            $table->string('table_prefix', 64)->nullable();
            $table->string('uploads_path', 1024)->nullable();

            // حقائق المصدر المُكتشَفة (لوحة التدقيق — Step 2).
            $table->json('source_facts')->nullable();

            // معاينة الأثر + بوّابة التنفيذ الصلبة (Step 5). المعاينة «حالية» إذا
            // preview_generated_at >= mappings_updated_at؛ تغيّر التنسيب يُبطلها.
            $table->json('preview')->nullable();
            $table->timestamp('preview_generated_at')->nullable();
            $table->timestamp('mappings_updated_at')->nullable();
            $table->timestamp('approved_at')->nullable();

            // عدّادات تقدّم مُجمَّعة (سرعة اللوحة الحيّة — لا COUNT متكرّر على 84k).
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->unsignedInteger('done_items')->default(0);
            $table->unsignedInteger('partial_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->unsignedInteger('skipped_items')->default(0);
            $table->unsignedInteger('media_imported')->default(0);
            $table->unsignedInteger('media_reused')->default(0);

            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wp_migration_runs');
    }
};
