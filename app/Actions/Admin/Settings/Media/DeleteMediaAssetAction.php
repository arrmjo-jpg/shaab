<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings\Media;

use App\Models\MediaAsset;
use App\Settings\GeneralSettings;
use App\Settings\ThirdPartySettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class DeleteMediaAssetAction
{
    public function handle(MediaAsset $asset): JsonResponse
    {
        $collection = $asset->metadata['collection'] ?? null;
        $disk = $asset->disk;
        $path = $asset->path;

        // تنظيف مرجع الإعدادات + حذف السجل ذرّياً؛ حذف الملف يؤجَّل
        // لما بعد الالتزام تفادياً لفقد ملف رغم تراجع المعاملة.
        DB::transaction(function () use ($asset, $collection): void {
            if ($collection === 'branding') {
                $field = $asset->metadata['field'] ?? null;
                if ($field !== null) {
                    $settings = app(GeneralSettings::class);
                    $settings->{$field} = null;
                    $settings->save();
                }
            } elseif ($collection === 'firebase') {
                $settings = app(ThirdPartySettings::class);
                $settings->firebase_credentials_path = '';
                $settings->firebase_service_account_json = '';
                $settings->save();
            }

            $asset->delete();
        });

        // بعد الالتزام: احذف الملف وأبطل كاش الإعدادات
        Storage::disk($disk)->delete($path);

        if (in_array($collection, ['branding', 'firebase'], true)) {
            Cache::tags(['settings'])->flush();
        }

        return ApiResponse::success(__('media.deleted'));
    }
}
