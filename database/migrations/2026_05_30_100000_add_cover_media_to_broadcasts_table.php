<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * غلاف البثّ من مكتبة الوسائط المركزية (رفع مباشر أو اختيار من المكتبة) — مرآة
 * broadcast_categories.cover_media_id. النطاق يبقى «بثّ خارجي موثوق» للمصدر فقط؛ هذا
 * للفنّ (غلاف العدّ التنازلي/المنتهي/مشاركة OG) لا للمصدر. لا يُلغي رابط البوستر
 * الخارجي (poster_path/thumbnail_path) — يبقيان كاحتياطٍ خارجيّ اختياري (nullOnDelete:
 * حذف الأصل يُفرّغ الرابط فقط دون كسر البثّ).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->foreignId('cover_media_id')->nullable()->after('poster_path')
                ->constrained('media_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('broadcasts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('cover_media_id');
        });
    }
};
