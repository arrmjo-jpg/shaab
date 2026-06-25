<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * فيديو خارجي كأصل مكتبة مركزي (P9 — Wave 2). لا نموذج منفصل: الفيديو
 * الخارجي صفّ في media_assets بـ kind='external' وبيانات المزوّد.
 *
 *   kind        : file | external
 *   provider    : youtube | vimeo | tiktok | instagram | facebook | x | mp4
 *   provider_id : معرّف الفيديو لدى المزوّد (إن وُجد)
 *   embed_url   : رابط التضمين الجاهز (<iframe>/<video>)
 *   source_url  : الرابط الأصلي كما لصقه المحرّر
 *   poster_url  : صورة مصغّرة اختيارية (مثل YouTube)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->string('kind', 20)->default('file')->after('uuid')->index();
            $table->string('provider', 20)->nullable()->after('kind');
            $table->string('provider_id')->nullable()->after('provider');
            $table->string('embed_url', 1024)->nullable()->after('provider_id');
            $table->string('source_url', 1024)->nullable()->after('embed_url');
            $table->string('poster_url', 1024)->nullable()->after('source_url');
        });
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex(['kind']);
            $table->dropColumn(['kind', 'provider', 'provider_id', 'embed_url', 'source_url', 'poster_url']);
        });
    }
};
