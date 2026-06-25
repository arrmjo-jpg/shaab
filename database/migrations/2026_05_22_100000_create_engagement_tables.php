<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * منصّة التفاعل الموحّدة (polymorphic) — تخدم كل أنواع المحتوى الحالية
 * والمستقبلية (أخبار/مقالات/تغطية/ريلز/فيديو/بث/نسخ مطبوعة) عبر هدف عام
 * (engageable_type + engageable_id). لا جداول لكل نوع.
 *
 * - engagement_counters : عدّادات مُجمَّعة لكل هدف (likes/dislikes/favorites/views)
 *   تتجنّب العدّ وقت التشغيل (أداء).
 * - engagements         : سجلّ الفاعل (من تفاعل بماذا) لفرض «تفاعل واحد» ومنع
 *   التكرار. هجين: مستخدم مُصادَق (user_id) أو زائر (fingerprint)، موحَّد في
 *   actor_key لقيد فرادة نظيف.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('engagement_counters', function (Blueprint $table): void {
            $table->id();
            $table->string('engageable_type');
            $table->unsignedBigInteger('engageable_id');
            $table->unsignedBigInteger('likes')->default(0);
            $table->unsignedBigInteger('dislikes')->default(0);
            $table->unsignedBigInteger('favorites')->default(0);
            $table->unsignedBigInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['engageable_type', 'engageable_id'], 'engagement_counters_target_unique');
        });

        Schema::create('engagements', function (Blueprint $table): void {
            $table->id();
            $table->string('engageable_type');
            $table->unsignedBigInteger('engageable_id');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fingerprint', 64)->nullable();
            // هوية الفاعل الموحّدة: "u{id}" للمصادَق أو "f{hash}" للزائر.
            $table->string('actor_key', 80);
            $table->string('type', 16);
            $table->timestamps();

            $table->index(['engageable_type', 'engageable_id', 'type'], 'engagements_target_type_idx');
            $table->unique(
                ['engageable_type', 'engageable_id', 'type', 'actor_key'],
                'engagements_unique_actor',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('engagements');
        Schema::dropIfExists('engagement_counters');
    }
};
