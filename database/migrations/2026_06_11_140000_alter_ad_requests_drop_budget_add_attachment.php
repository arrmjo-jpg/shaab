<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * طلبات الإعلان: حذف «الميزانية» نهائيًّا + إضافة مرفق خاصّ (صورة أو ZIP) يُحفَظ على القرص
 * الخاصّ (local) ويُخدَم تنزيلًا للإدارة فقط. لا HTML خامّ في القاعدة، لا فكّ ضغط، لا تنفيذ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_requests', function (Blueprint $table): void {
            if (Schema::hasColumn('ad_requests', 'budget')) {
                $table->dropColumn('budget');
            }

            $table->string('attachment_path')->nullable()->after('description');   // مسار خاصّ (لا يُكشَف)
            $table->string('attachment_name')->nullable()->after('attachment_path'); // الاسم الأصليّ (للتنزيل)
            $table->string('attachment_mime')->nullable()->after('attachment_name');
        });
    }

    public function down(): void
    {
        Schema::table('ad_requests', function (Blueprint $table): void {
            $table->dropColumn(['attachment_path', 'attachment_name', 'attachment_mime']);
            $table->string('budget')->nullable()->after('ad_type');
        });
    }
};
