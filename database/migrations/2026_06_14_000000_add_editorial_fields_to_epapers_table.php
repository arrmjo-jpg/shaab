<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * حقول تحريريّة منتقاة لكل عدد (P-pending): نشرة اليوم + أبرز المختارات + فهرس «داخل هذا
 * العدد». كلّها JSON nullable — متوافقة مع الأعداد القديمة (تبقى null ⇒ الواجهة تعرض
 * حالتها الفارغة الصادثة). الغلاف لا عمود له (يُشتقّ من media_asset.conversions['cover']).
 * forward-only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('epapers', function (Blueprint $table): void {
            $table->json('brief_points')->nullable()->after('summary');
            $table->json('highlights')->nullable()->after('brief_points');
            $table->json('inside_this_issue')->nullable()->after('highlights');
        });
    }

    public function down(): void
    {
        Schema::table('epapers', function (Blueprint $table): void {
            $table->dropColumn(['brief_points', 'highlights', 'inside_this_issue']);
        });
    }
};
