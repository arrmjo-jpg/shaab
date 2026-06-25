<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * توسعة نموذج الإسناد المشترك (article_media) ليملك تحديثات التغطية الحيّة
 * أيضاً — دون نظام وسائط منفصل (Wave: Live Coverage media).
 *
 * المالك إمّا مقال (article_id) أو تحديث تغطية حيّة (live_update_id). صف واحد
 * يحمل أحد المرجعين. متوافق رجعياً: صفوف المقالات الحالية تبقى كما هي.
 */
return new class extends Migration
{
    public function up(): void
    {
        // المقال لم يعُد المالك الوحيد — اجعله اختيارياً.
        Schema::table('article_media', function (Blueprint $table): void {
            $table->foreignId('article_id')->nullable()->change();
        });

        Schema::table('article_media', function (Blueprint $table): void {
            $table->foreignId('live_update_id')->nullable()->after('article_id')
                ->constrained('article_live_updates')->cascadeOnDelete();

            $table->unique(['live_update_id', 'collection', 'media_asset_id'], 'art_media_live_unique');
            $table->index(['live_update_id', 'collection', 'position'], 'art_media_live_pos_idx');
        });
    }

    public function down(): void
    {
        Schema::table('article_media', function (Blueprint $table): void {
            $table->dropUnique('art_media_live_unique');
            $table->dropIndex('art_media_live_pos_idx');
            $table->dropConstrainedForeignId('live_update_id');
        });

        Schema::table('article_media', function (Blueprint $table): void {
            $table->foreignId('article_id')->nullable(false)->change();
        });
    }
};
