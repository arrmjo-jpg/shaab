<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * استطلاعات الرأي (Polls) — سؤال واحد + خياراته، مع نافذة جدولة وحالة تفعيل. التصويت
 * العام والنتائج في مراحل لاحقة (Phase 2+). soft delete للسلّة/الاسترجاع. التفعيل
 * (is_active) لا يُضبط عند الإنشاء/التعديل — يتغيّر فقط عبر إجراء نشر مستقلّ (polls.publish).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('question', 500);
            $table->boolean('allow_multiple')->default(false);
            $table->boolean('is_active')->default(false)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            // مُخزَّنة وقابلة للتحرير في Phase 1؛ الفرض وقت التصويت يأتي في Phase 2.
            $table->string('audience_mode', 20)->default('public');
            $table->string('result_visibility', 20)->default('after_vote');

            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->index(['starts_at', 'ends_at'], 'polls_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('polls');
    }
};
