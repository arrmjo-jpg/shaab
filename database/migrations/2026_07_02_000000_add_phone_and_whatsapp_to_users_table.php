<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * إضافة رقم الهاتف + علم الاشتراك في حملات واتساب إلى المستخدمين.
 * - phone: نصّ E.164 (يُطبَّع في الـ Action)؛ اختياريّ، بلا قيد تفرّد (قد يتشارك حسابان رقماً).
 * - whatsapp_subscribed: سجلّ موافقة المستخدم (مرآة)؛ مصدر الحقيقة للإرسال يبقى whatsapp_contacts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('phone', 30)->nullable()->after('email');
            $table->boolean('whatsapp_subscribed')->default(false)->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['phone', 'whatsapp_subscribed']);
        });
    }
};
