<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خيارات الاستطلاع. votes_count عدّاد أداء مُزال-التطبيع (مصدر الحقيقة = poll_votes +
 * poll_vote_options، قابل لإعادة البناء). لا soft delete: الخيار غير القابل للتصويت
 * يُحذف صلباً عند التحرير، والخيار الذي يملك أصواتاً يُمنع حذفه (سلامة) — يُفرَض في الـ Action.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $table->string('label', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedBigInteger('votes_count')->default(0);
            $table->timestamps();

            $table->index(['poll_id', 'sort_order'], 'poll_options_poll_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_options');
    }
};
