<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تثبيت الخبر (is_pinned): علم تحريري يجعل الخبر يتصدّر سياقه — أوّل عنصر في
 * قائمة قسمه وفي الخلاصات (هيرو/الأحدث) — بصرف النظر عن تاريخ النشر.
 *
 * الترتيب العام يصبح: is_pinned DESC ثمّ published_at DESC، فالمثبَّت يطفو للقمّة.
 * يُفهرَس العمود لأنّه (أ) يُفلتَر بـ =true في الإدارة، (ب) عمود ترتيب قائد في
 * القراءة العامة — القيمة true نادرة فالفهرس انتقائيّ ومفيد.
 *
 * فهرسة online آمنة إنتاجاً (ALGORITHM=INPLACE LOCK=NONE) — لا قفل جدول. إضافة
 * عمود boolean بقيمة افتراضية في MySQL 8 = ALGORITHM=INSTANT (لحظيّ، لا نسخ).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('articles', 'is_pinned')) {
            return; // idempotent
        }

        $mysql = DB::connection()->getDriverName() === 'mysql';

        Schema::table('articles', function (Blueprint $t) use ($mysql): void {
            $col = $t->boolean('is_pinned')->default(false);
            if ($mysql) {
                $col->after('is_breaking');
            } else {
                // SQLite (اختبارات): الفهرس عبر Blueprint مباشرةً.
                $t->index('is_pinned', 'articles_is_pinned_index');
            }
        });

        if ($mysql) {
            DB::statement('CREATE INDEX articles_is_pinned_index ON articles (is_pinned) ALGORITHM=INPLACE LOCK=NONE');
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('articles', 'is_pinned')) {
            return;
        }

        Schema::table('articles', function (Blueprint $t): void {
            $t->dropIndex('articles_is_pinned_index');
            $t->dropColumn('is_pinned');
        });
    }
};
