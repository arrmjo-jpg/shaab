<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Health\ChannelHealthProbe;
use App\Modules\Notifications\Http\Resources\ChannelHealthResource;
use App\Modules\Notifications\Models\NotificationChannelHealth;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * صحّة القنوات — يُحدَّث دوريًّا (notifications:probe-channels كلّ 10د) + عند كلّ فشل إرسال.
 * effective_state = resolve(enabled, configured, healthy). index يقرأ السجلّ؛ probe يفرض فحصًا فوريًّا.
 */
final class HealthController extends Controller
{
    public function index(): JsonResponse
    {
        $rows = NotificationChannelHealth::query()->orderBy('channel')->get();

        return ApiResponse::success(data: ChannelHealthResource::collection($rows)->resolve());
    }

    public function probe(ChannelHealthProbe $probe): JsonResponse
    {
        $probe->probeAll();

        $rows = NotificationChannelHealth::query()->orderBy('channel')->get();

        return ApiResponse::success(
            message: 'تم فحص جميع القنوات',
            data: ChannelHealthResource::collection($rows)->resolve(),
        );
    }
}
