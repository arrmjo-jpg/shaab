<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * تجميع يوميّ للتفاعل (تيليمتري تقدّميّ، إلى-الأمام فقط) — مرآة polymorphic لجدول
 * engagement_counters لكن ببُعد زمني (يوم). يُغذّى من نقاط الكتابة القائمة بلا بنية
 * جديدة: المشاهدات عبر engagement:flush-views (مُجمَّعة)، والتفاعلات عبر
 * EngagementService (منخفض الحجم). يمكّن مخطّطات «المشاهدات/التفاعل عبر الزمن» ومصادر
 * الزيارات (تصنيف خشن للمُحيل) بصدق — بلا أعمدة وهمية، بلا أثر رجعيّ (يبدأ التجميع الآن).
 *
 *  • views: عدّاد موجب فقط (إجمالي مشاهدات اليوم).
 *  • likes/dislikes/favorites: دلتا صافية مُوقَّعة (قد تكون سالبة عند التبديل/الإلغاء).
 *  • views_{channel}: تفصيل المشاهدات حسب قناة الزيارة (مجموعها ≈ views).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->string('engageable_type');
            $table->unsignedBigInteger('engageable_id');
            $table->date('day');

            $table->unsignedBigInteger('views')->default(0);
            $table->integer('likes')->default(0);     // دلتا مُوقَّعة
            $table->integer('dislikes')->default(0);
            $table->integer('favorites')->default(0);

            // تفصيل قنوات الزيارة (مشاهدات) — تصنيف خشن عند منارة المشاهدة.
            $table->unsignedBigInteger('views_direct')->default(0);
            $table->unsignedBigInteger('views_internal')->default(0);
            $table->unsignedBigInteger('views_search')->default(0);
            $table->unsignedBigInteger('views_social')->default(0);
            $table->unsignedBigInteger('views_referral')->default(0);

            $table->timestamps();

            // فرادة + مسار الاستعلام السائد: (نوع، مُعرّف، يوم) للنطاقات الزمنية.
            $table->unique(['engageable_type', 'engageable_id', 'day'], 'content_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_daily_stats');
    }
};
