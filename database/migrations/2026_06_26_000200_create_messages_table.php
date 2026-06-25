<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * رسائل الشات — تيّار أحداث عالي التردّد (event stream)، وليست كياناً تجارياً يُدقَّق.
 * body نصّ صِرف فقط (لا HTML إطلاقاً — يُهرَّب عند العرض). المرفقات عبر المكتبة
 * المركزية (attachment_asset_id → media_assets) — لا مسار رفع موازٍ.
 *
 * user_id بـ nullOnDelete: تُحفَظ الرسالة كتاريخ حتى لو حُذف المرسِل (يُعرَض كـ«محذوف»).
 * مستثناة من AuditsChanges عمداً (قرار معماريّ: write-amplification بلا قيمة تحليلية).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('conversation_id')->constrained('conversations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->foreignId('attachment_asset_id')->nullable()->constrained('media_assets')->nullOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // ترقيم المؤشّر (cursor) للتاريخ داخل محادثة.
            $table->index(['conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
