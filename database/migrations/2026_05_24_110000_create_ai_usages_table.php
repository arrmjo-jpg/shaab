<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ استخدام الذكاء الاصطناعي — قابل للاستعلام (لرؤية التكلفة التشغيلية وفرض
 * الحدود). صفّ لكل عملية ذكاء فعلية: المستخدم/المزوّد/النوع/التوكِنات المقدّرة/
 * التكلفة المقدّرة/الوقت. لا يحوي محتوى حسّاساً (لا نصوص/تلقينات/مخرجات).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_usages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('provider', 20);   // openai|gemini
            $table->string('action', 30);      // headlines|excerpt|rewrite|tags|seo|analyze
            $table->string('source', 10);      // ai (only real AI calls are recorded)
            $table->unsignedInteger('tokens')->default(0);            // مقدّرة
            $table->decimal('estimated_cost', 12, 6)->default(0);     // USD مقدّرة
            $table->timestamp('created_at')->nullable()->index();

            $table->index(['user_id', 'created_at']);
            $table->index(['provider', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_usages');
    }
};
