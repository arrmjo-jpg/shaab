<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Http\Resources\Admin\Settings\ThirdPartySettingsResource;
use App\Settings\ThirdPartySettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class UploadFirebaseCredentialsAction
{
    public function handle(UploadedFile $file): JsonResponse
    {
        $contents = (string) $file->get();
        $decoded = json_decode($contents, true);

        if (! is_array($decoded) || empty($decoded['project_id'])) {
            return ApiResponse::error(__('setting.firebase_invalid_json'), [], 422);
        }

        $dir = storage_path('app/private/firebase');
        $path = $dir.'/service-account.json';
        File::ensureDirectoryExists($dir);
        File::put($path, $contents);

        $settings = app(ThirdPartySettings::class);
        $settings->firebase_service_account_json = $contents; // مشفّر عبر encrypted()
        $settings->firebase_project_id = (string) $decoded['project_id'];
        $settings->firebase_credentials_path = $path;
        $settings->save();

        Cache::tags(['settings'])->flush();

        return ApiResponse::success(
            __('setting.firebase_uploaded'),
            new ThirdPartySettingsResource($settings)
        );
    }
}
