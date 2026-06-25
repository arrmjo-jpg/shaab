<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Http\Resources\Admin\Settings\MediaStorageSettingsResource;
use App\Settings\MediaStorageSettings;
use App\Support\Media\RemoteStorage;
use App\Support\Media\RemoteStorageHealth;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * حالة التخزين الهجين للوحة: الإعدادات (أسرار مُقنَّعة) + عدّادات حالة المزامنة
 * الحيّة + صحّة المرآة. غير مُخزَّن مؤقتاً (المتراكم حيّ).
 */
class ShowMediaStorageStatusAction
{
    public function handle(): JsonResponse
    {
        $counts = DB::table('media_assets')
            ->groupBy('remote_sync_status')
            ->selectRaw('remote_sync_status, COUNT(*) as aggregate')
            ->pluck('aggregate', 'remote_sync_status');

        $pending = (int) ($counts['pending'] ?? 0);
        $failed = (int) ($counts['failed'] ?? 0);

        // أحدث الأصول الفاشلة مع سبب الفشل — يُظهر للأدمن «لماذا» لا «failed» فقط.
        $failures = DB::table('media_assets')
            ->where('remote_sync_status', 'failed')
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'original_name', 'remote_sync_error', 'last_remote_sync_at'])
            ->map(fn ($r): array => [
                'id' => $r->id,
                'name' => $r->original_name,
                'error' => $r->remote_sync_error,
                'at' => $r->last_remote_sync_at,
            ])
            ->all();

        return ApiResponse::success(data: [
            'settings' => (new MediaStorageSettingsResource(app(MediaStorageSettings::class)))->resolve(),
            'backlog' => [
                'pending' => $pending,
                'syncing' => (int) ($counts['syncing'] ?? 0),
                'failed' => $failed,
                'synced' => (int) ($counts['synced'] ?? 0),
                'disabled' => (int) ($counts['disabled'] ?? 0),
                'unsynced' => $pending + $failed, // عدّاد «وسائط غير متزامنة» للبانر
            ],
            'failures' => $failures,
            'remote_healthy' => RemoteStorage::enabled() ? RemoteStorageHealth::isHealthy() : null,
        ]);
    }
}
