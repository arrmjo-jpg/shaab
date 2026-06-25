<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خط زمني التغطية الحيّة (P8) — تحديثات تابعة لمقال type=live.
 * إعادة استخدام بنية المحتوى: content_json (TipTap) + content (HTML مشتقّ).
 * ترتيب العرض: المثبّت أولاً ثم زمن الحدث تنازلياً (pinned-first + chronological).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('article_live_updates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('article_id')->constrained('articles')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->string('title', 200)->nullable();
            $table->json('content_json');
            $table->longText('content')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('happened_at')->index();
            $table->timestamps();

            // الفهرس الأساسي للترتيب: مقال + مثبّت + زمن الحدث
            $table->index(['article_id', 'is_pinned', 'happened_at'], 'live_updates_article_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_live_updates');
    }
};
