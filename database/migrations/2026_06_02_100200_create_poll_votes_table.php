<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بطاقة تصويت واحدة لكل ناخب لكل استطلاع (حقيقة ثابتة لا تُعدّل). voter_hash تجزئة هويّة
 * أحادية الاتجاه (لا IP خام). الفرادة (poll_id, voter_hash) هي ضمانة منع التكرار الصلبة.
 * مخطّط فقط في Phase 1 — لا كتابة هنا حتى التصويت العام (Phase 2).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
            $table->string('voter_hash', 64);
            $table->timestamp('created_at')->nullable();

            $table->unique(['poll_id', 'voter_hash'], 'poll_votes_poll_voter_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
    }
};
