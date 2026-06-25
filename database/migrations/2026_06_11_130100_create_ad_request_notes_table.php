<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ملاحظات داخليّة لطلبات الإعلان — **سجلّ كامل بتاريخ ومستخدم** (قرار المراجعة: لا عمود
 * internal_note واحد يُكتَب فوقه). جدول ابن (كـ messages تحت conversations) — لا نظام موازٍ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_request_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('ad_request_id')->constrained('ad_requests')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['ad_request_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_request_notes');
    }
};
