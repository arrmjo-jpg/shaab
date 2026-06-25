<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ أنواع أحداث الإشعارات (Event-First). المصادر الأربعة متكافئة عبر العمود source.
 * كلّ حدث يحمل سياسته الافتراضيّة (الأولويّة + التفعيل) ويُربط بمصفوفة القنوات.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 80)->unique(); // article_published|breaking_news|daily_digest|system_alert…
            $table->string('source', 20)->index(); // domain|scheduled|manual|system
            $table->string('category', 50)->nullable();
            $table->string('default_priority', 20)->default('normal'); // critical|high|normal|low
            $table->boolean('enabled')->default(true);
            $table->boolean('archived')->default(false); // حُذف من EventCatalog ⇒ يُؤرشَف (لا يُحذف من DB)
            $table->boolean('is_user_visible')->default(true); // يُخفي التقنيّ/الحسّاس من الواجهات
            $table->boolean('supports_manual_dispatch')->default(false); // يظهر في واجهة الحملات اليدويّة
            $table->string('description', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
