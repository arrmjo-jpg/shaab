<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مزامنة عمود صورة عضو الفريق مع المكتبة المركزية (MediaAsset).
 *
 * السياق: نسخة Slice 1 الأصلية أنشأت العمود النصّي `avatar`؛ ثم اعتُمد ربط
 * MediaAsset (`avatar_asset_id`). البيئات التي شغّلت النسخة الأصلية تحمل `avatar`،
 * بينما تثبيت جديد (migration الإنشاء المحدّث) يحمل `avatar_asset_id` أصلاً.
 *
 * لذا هذا الـ migration محميّ بـ hasColumn ليكون idempotent: يطبّق التبديل على
 * البيئات القديمة، ويكون no-op على التثبيت الجديد. لا فقدان بيانات (الميزة لم تكن
 * تعمل بعد — العمود القديم فارغ دائماً).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('team_members', 'avatar')) {
            Schema::table('team_members', function (Blueprint $table): void {
                $table->dropColumn('avatar');
            });
        }

        if (! Schema::hasColumn('team_members', 'avatar_asset_id')) {
            Schema::table('team_members', function (Blueprint $table): void {
                $table->foreignId('avatar_asset_id')->nullable()->after('bio')
                    ->constrained('media_assets')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('team_members', 'avatar_asset_id')) {
            Schema::table('team_members', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('avatar_asset_id');
            });
        }

        if (! Schema::hasColumn('team_members', 'avatar')) {
            Schema::table('team_members', function (Blueprint $table): void {
                $table->string('avatar', 500)->nullable()->after('bio');
            });
        }
    }
};
