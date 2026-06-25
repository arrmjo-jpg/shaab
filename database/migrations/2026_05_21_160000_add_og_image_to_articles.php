<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * صورة مشاركة اجتماعية مخصّصة (og:image) — أصل من المكتبة المركزية، يتجاوز
 * الغلاف لأغراض المشاركة. يخصّ كل مقال/حدث مباشر كمحتوى مستقلّ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->foreignId('og_image_id')->nullable()->after('robots')
                ->constrained('media_assets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('og_image_id');
        });
    }
};
