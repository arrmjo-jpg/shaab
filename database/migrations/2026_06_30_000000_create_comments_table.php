<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تعليقات المحتوى (polymorphic) — أساس نظام التعليقات والإشراف. هدف عام
 * (commentable_type + commentable_id) يخدم المقالات والمستقبل. هجين: مستخدم
 * مُصادَق (user_id) أو زائر (author_name/email). حالة إشراف + ردود متداخلة.
 * هذه الشريحة تُسلّم النطاق + القراءة فقط (لا اعتماد/رفض/حذف بعد).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table): void {
            $table->id();
            $table->morphs('commentable'); // (commentable_type, commentable_id) + index
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('comments')->cascadeOnDelete();
            $table->string('author_name', 120)->nullable();
            $table->string('author_email', 190)->nullable();
            $table->text('body');
            $table->string('status', 16)->default('pending')->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
