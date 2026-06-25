<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * محادثات الشات الداخليّ بين المدراء — طبقة تواصل داخل الـ CMS (ليست محتوى عام).
 * ثلاثة أنواع موحّدة البنية: general (غرفة واحدة للنظام) · direct (1↔1) · group.
 *
 * dm_key: مفتاح حتميّ "min-max" لمعرّفَي طرفَي المحادثة المباشرة — قيد فريد يمنع
 * تكرار غرف الـ DM (null للمجموعات/العامة). last_message_at مُزال-تطبيعه لفرز القائمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('type', 20)->index();          // general | direct | group
            $table->string('title', 150)->nullable();      // للمجموعات
            $table->string('dm_key', 40)->nullable()->unique(); // فرادة الـ DM (a-b)
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_message_at')->nullable()->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
