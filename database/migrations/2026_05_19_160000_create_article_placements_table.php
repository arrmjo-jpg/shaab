<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * التنسيبات التحريرية (P5) — بنية مخصّصة بدل أعلام منطقية.
 * نافذة زمنية عامة لكل تنسيب: starts_at/ends_at nullable (قرار مقفول).
 */
return new class extends Migration
{
    public function up(): void
    {
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

            // مقال واحد لا يُنسَّب مرّتين في نفس المنطقة/اللغة
            $table->unique(['zone', 'locale', 'article_id'], 'placements_zone_locale_article_uq');
            $table->index(['zone', 'locale', 'position'], 'placements_zone_locale_pos_idx');
            $table->index(['zone', 'locale', 'starts_at', 'ends_at'], 'placements_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('article_placements');
    }
};
