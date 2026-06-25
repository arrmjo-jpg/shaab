<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * سجلّ المسارات القانونية القديمة لأعضاء الفريق — يلتقط canonical السابق عند تغيّر
 * slug لعضو نشِط (مرئيّ للعامّة)، فيُمكِّن إعادة توجيه 301 (حفظ قيمة SEO ومنع كسر
 * الروابط). مرآة page_url_history.
 *
 * التصميم: old_path → team_member_id (مؤشّر مباشر للكيان، لا سلسلة old→new). هذا
 * يجعل الحلّ O(1) وخالياً من الحلقات بنيوياً (الـ canonical يُشتقّ دائماً من الـ slug
 * الحالي للعضو)، فلا حاجة لعدّاد عمق — يكفي حارس self-reference في المُحلِّل.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_member_url_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('team_member_id')->constrained('team_members')->cascadeOnDelete();
            $table->string('old_path');
            $table->string('reason', 40)->nullable();
            $table->timestamps();

            // فرادة قاعديّة على old_path — تمنع التكرار وتدعم firstOrCreate كحارس
            // نهائي بجانب الالتقاط الشرطي في UpdateTeamMemberAction.
            $table->unique('old_path');
            $table->index('team_member_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_member_url_history');
    }
};
