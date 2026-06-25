<?php

declare(strict_types=1);

namespace App\Modules\CDN\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\CDN\Actions\PurgeAllAction;
use App\Modules\CDN\Actions\PurgeUrlsAction;
use App\Modules\CDN\Actions\ShowCdnStatusAction;
use App\Modules\CDN\Actions\TestCdnConnectionAction;
use App\Modules\CDN\Actions\UpdateCdnSettingsAction;
use App\Modules\CDN\Http\Requests\PurgeUrlsRequest;
use App\Modules\CDN\Http\Requests\UpdateCdnSettingsRequest;
use Illuminate\Http\JsonResponse;

class CdnController extends Controller
{
    public function status(): JsonResponse
    {
        return (new ShowCdnStatusAction)->handle();
    }

    public function updateSettings(UpdateCdnSettingsRequest $request): JsonResponse
    {
        return (new UpdateCdnSettingsAction)->handle($request->validated());
    }

    public function test(): JsonResponse
    {
        return (new TestCdnConnectionAction)->handle();
    }

    public function purge(PurgeUrlsRequest $request): JsonResponse
    {
        return (new PurgeUrlsAction)->handle($request->validated('urls'));
    }

    public function purgeAll(): JsonResponse
    {
        return (new PurgeAllAction)->handle();
    }
}
