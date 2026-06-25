<?php

declare(strict_types=1);

namespace App\Actions\Admin\Settings;

use App\Settings\MediaStorageSettings;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * يختبر اتصال التخزين البعيد (write/read/delete) قبل الحفظ/التفعيل، باستخدام
 * الاعتماديات المُرسَلة من النموذج. السرّ/المفتاح المُقنَّع أو الفارغ يرجع للمحفوظ
 * (كي يُختبَر دون إعادة إدخال). يبني قرصاً مؤقتاً (throw=true لإظهار الخطأ).
 */
class TestRemoteStorageConnectionAction
{
    public function handle(array $input): JsonResponse
    {
        $saved = app(MediaStorageSettings::class);

        $key = $this->resolve($input['remote_key'] ?? null, $saved->remote_key);
        $secret = $this->resolve($input['remote_secret'] ?? null, $saved->remote_secret);
        $bucket = $input['remote_bucket'] ?? $saved->remote_bucket;
        $endpoint = $input['remote_endpoint'] ?? $saved->remote_endpoint;

        if ($key === '' || $secret === '' || $bucket === '') {
            return ApiResponse::error(__('setting.media_test_missing'), [], 422);
        }

        $config = [
            'driver' => 's3',
            'key' => $key,
            'secret' => $secret,
            'region' => ($input['remote_region'] ?? $saved->remote_region) ?: 'auto',
            'bucket' => $bucket,
            'endpoint' => ($input['remote_endpoint'] ?? $saved->remote_endpoint) ?: null,
            'use_path_style_endpoint' => (bool) ($input['remote_use_path_style'] ?? $saved->remote_use_path_style),
            'throw' => true,
        ];

        $probeKey = 'healthcheck/'.Str::uuid()->toString().'.txt';

        try {
            $disk = Storage::build($config);
            $disk->put($probeKey, 'alphacms-connection-test', ['CacheControl' => 'no-store']);
            $ok = $disk->get($probeKey) === 'alphacms-connection-test';
            $disk->delete($probeKey);
        } catch (Throwable $e) {
            return ApiResponse::error(__('setting.media_test_failed'), [
                'detail' => mb_substr($e->getMessage(), 0, 300),
            ], 422);
        }

        return $ok
            ? ApiResponse::success(__('setting.media_test_success'))
            : ApiResponse::error(__('setting.media_test_failed'), [], 422);
    }

    /** يرجع للمحفوظ عند تقنيع/فراغ القيمة المُرسَلة. */
    private function resolve(?string $submitted, string $saved): string
    {
        if ($submitted === null || $submitted === '' || $submitted === '********') {
            return $saved;
        }

        return $submitted;
    }
}
