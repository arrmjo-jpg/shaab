<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خيارات البطاقة المُطبَّعة (أيّ خيار/خيارات اختارها الناخب). بطاقة أحادية الاختيار ⇒ صفّ
 * واحد؛ متعدّدة ⇒ عدّة صفوف. مصدر الحقيقة لإعادة بناء votes_count. فهرس poll_option_id
 * يُسرّع حارس «هل للخيار أصوات؟» (منع حذف خيار مُصوَّت). مخطّط فقط في Phase 1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_vote_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('poll_vote_id')->constrained('poll_votes')->cascadeOnDelete();
            $table->foreignId('poll_option_id')->constrained('poll_options')->cascadeOnDelete();

            $table->unique(['poll_vote_id', 'poll_option_id'], 'poll_vote_options_unique');
            $table->index('poll_option_id', 'poll_vote_options_option_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_vote_options');
    }
};
