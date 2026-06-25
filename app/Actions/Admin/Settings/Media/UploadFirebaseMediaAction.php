<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings\Media;

use App\Enums\MediaVisibility;
use App\Http\Resources\Admin\Settings\MediaResource;
use App\Models\MediaAsset;
use App\Models\User;
use App\Settings\ThirdPartySettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class UploadFirebaseMediaAction
{
    private const REQUIRED_KEYS = ['type', 'project_id', 'private_key', 'client_email'];

    private const PATH = 'private/firebase/service-account.json';

    public function handle(UploadedFile $file, User $actor): JsonResponse
    {
        $contents = (string) $file->get();
        $decoded = json_decode($contents, true);

        if (! $this->isValidServiceAccount($decoded)) {
            return ApiResponse::error(__('media.firebase_invalid'), [], 422);
        }

        // حذف أصل Firebase السابق بأمان (خاص دائماً)
        MediaAsset::query()
            ->where('metadata->collection', 'firebase')
            ->get()
            ->each(function (MediaAsset $asset): void {
                Storage::disk($asset->disk)->delete($asset->path);
                $asset->delete();
            });

        // تخزين خاص — لا يُكشف عبر القرص العام أبداً
        Storage::disk('local')->put(self::PATH, $contents);

        $uuid = (string) Str::uuid();
        MediaAsset::create([
            'uuid' => $uuid,
            'disk' => 'local',
            'path' => self::PATH,
            'filename' => 'service-account.json',
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => 'application/json',
            'extension' => 'json',
            'size' => $file->getSize(),
            'width' => null,
            'height' => null,
            'metadata' => ['collection' => 'firebase'],
            'visibility' => MediaVisibility::Private,
            'uploaded_by' => $actor->id,
        ]);

        $settings = app(ThirdPartySettings::class);
        $settings->firebase_service_account_json = $contents; // مشفّر عبر encrypted()
        $settings->firebase_project_id = (string) $decoded['project_id'];
        $settings->firebase_credentials_path = storage_path('app/'.self::PATH);
        $settings->save();

        Cache::tags(['settings'])->flush();

        return ApiResponse::success(
            __('media.firebase_uploaded'),
            new MediaResource(MediaAsset::where('uuid', $uuid)->first())
        );
    }

    private function isValidServiceAccount(mixed $decoded): bool
    {
        if (! is_array($decoded)) {
            return false;
        }

        foreach (self::REQUIRED_KEYS as $key) {
            if (empty($decoded[$key])) {
                return false;
            }
        }

        return $decoded['type'] === 'service_account';
    }
}
