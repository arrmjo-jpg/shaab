<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تخزين هجين (محلّي canonical + مرآة بعيدة اختيارية) — حالة المزامنة لكل أصل.
 *
 * الأعمدة تشغيليّة (تُحدَّث بكثرة من وظائف المرآة) فلا تدخل تدقيق activity_log
 * (ليست في $auditAttributes). الـ backfill أدناه لمرّة واحدة على أعمدة غير
 * مُدقَّقة، فاستخدام DB:: مقصود (يتفادى ضجيج التدقيق + لا I/O للملفات هنا؛
 * توطين الملفات الفعلي يتم عبر media:repair:remote --pull لاحقاً).
 */
return new class extends Migration
{
    /** أقراص محلّية معتبرة canonical. */
    private const LOCAL_DISKS = ['uploads', 'public', 'local'];

    public function up(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->boolean('stored_local')->default(true)->after('disk');
            $table->boolean('stored_remote')->default(false)->after('stored_local');
            $table->string('remote_path')->nullable()->after('stored_remote');
            $table->string('remote_sync_status', 20)->nullable()->after('remote_path');
            $table->text('remote_sync_error')->nullable()->after('remote_sync_status');
            $table->timestamp('last_remote_sync_at')->nullable()->after('remote_sync_error');
            $table->string('preferred_delivery', 10)->default('auto')->after('last_remote_sync_at');

            $table->index('remote_sync_status', 'media_assets_remote_sync_idx');
        });

        // ── backfill الأعلام من القرص الحالي (بلا نقل ملفات) ──

        // أصول محلّية (canonical موجود) — مرشّحة للمزامنة عند تفعيل المرآة.
        DB::table('media_assets')
            ->whereIn('disk', self::LOCAL_DISKS)
            ->update([
                'stored_local' => true,
                'stored_remote' => false,
                'remote_sync_status' => 'pending',
                'preferred_delivery' => 'auto',
            ]);

        // أصول بعيدة فقط (مثل s3/R2 من فترة MEDIA_DISK=s3) — تُخدَم من البعيد
        // الآن وتُوطَّن لاحقاً (media:repair:remote --pull). لا فقدان وسائط.
        DB::table('media_assets')
            ->whereNotIn('disk', self::LOCAL_DISKS)
            ->where('kind', '!=', 'external')
            ->update([
                'stored_local' => false,
                'stored_remote' => true,
                'remote_path' => DB::raw('path'),
                'remote_sync_status' => 'synced',
                'preferred_delivery' => 'remote',
            ]);

        // فيديو خارجي — لا ملفات على أي قرص.
        DB::table('media_assets')
            ->where('kind', 'external')
            ->update([
                'stored_local' => false,
                'stored_remote' => false,
                'remote_sync_status' => 'disabled',
                'preferred_delivery' => 'auto',
            ]);
    }

    public function down(): void
    {
        Schema::table('media_assets', function (Blueprint $table): void {
            $table->dropIndex('media_assets_remote_sync_idx');
            $table->dropColumn([
                'stored_local',
                'stored_remote',
                'remote_path',
                'remote_sync_status',
                'remote_sync_error',
                'last_remote_sync_at',
                'preferred_delivery',
            ]);
        });
    }
};
