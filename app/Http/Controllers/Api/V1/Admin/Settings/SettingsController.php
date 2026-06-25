<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Settings;

use App\Actions\Admin\Settings\ShowMediaStorageStatusAction;
use App\Actions\Admin\Settings\ShowSettingsGroupAction;
use App\Actions\Admin\Settings\ShowSettingsOverviewAction;
use App\Actions\Admin\Settings\SyncRemoteMediaNowAction;
use App\Actions\Admin\Settings\TestCdnConnectionAction;
use App\Actions\Admin\Settings\TestMailConnectionAction;
use App\Actions\Admin\Settings\TestOpenweatherConnectionAction;
use App\Actions\Admin\Settings\TestRemoteStorageConnectionAction;
use App\Actions\Admin\Settings\TestSportmonksConnectionAction;
use App\Actions\Admin\Settings\TestWhatsappConnectionAction;
use App\Actions\Admin\Settings\UpdateCdnSettingsAction;
use App\Actions\Admin\Settings\UpdateGeneralSettingsAction;
use App\Actions\Admin\Settings\UpdateMediaStorageSettingsAction;
use App\Actions\Admin\Settings\UpdateThirdPartySettingsAction;
use App\Actions\Admin\Settings\UploadBrandingAction;
use App\Actions\Admin\Settings\UploadFirebaseCredentialsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Settings\SendTestMailRequest;
use App\Http\Requests\Admin\Settings\TestRemoteStorageRequest;
use App\Http\Requests\Admin\Settings\UpdateCdnSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateGeneralSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateMediaStorageSettingsRequest;
use App\Http\Requests\Admin\Settings\UpdateThirdPartySettingsRequest;
use App\Http\Requests\Admin\Settings\UploadBrandingRequest;
use App\Http\Requests\Admin\Settings\UploadFirebaseRequest;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    public function overview(): JsonResponse
    {
        return (new ShowSettingsOverviewAction)->handle();
    }

    public function show(string $group): JsonResponse
    {
        return (new ShowSettingsGroupAction)->handle($group);
    }

    public function updateGeneral(UpdateGeneralSettingsRequest $request): JsonResponse
    {
        return (new UpdateGeneralSettingsAction)->handle($request->validated());
    }

    public function updateThirdParty(UpdateThirdPartySettingsRequest $request): JsonResponse
    {
        return (new UpdateThirdPartySettingsAction)->handle($request->validated());
    }

    public function updateCdn(UpdateCdnSettingsRequest $request): JsonResponse
    {
        return (new UpdateCdnSettingsAction)->handle($request->validated());
    }

    public function uploadBranding(UploadBrandingRequest $request): JsonResponse
    {
        $files = [];
        foreach (['logo_light', 'logo_dark', 'favicon', 'watermark_image'] as $field) {
            if ($request->hasFile($field)) {
                $files[$field] = $request->file($field);
            }
        }

        return (new UploadBrandingAction)->handle($files);
    }

    public function uploadFirebase(UploadFirebaseRequest $request): JsonResponse
    {
        return (new UploadFirebaseCredentialsAction)->handle($request->file('service_account'));
    }

    public function testMail(SendTestMailRequest $request): JsonResponse
    {
        return (new TestMailConnectionAction)->handle($request->validated('to'));
    }

    public function testCdn(): JsonResponse
    {
        return (new TestCdnConnectionAction)->handle();
    }

    public function testSportmonks(): JsonResponse
    {
        return (new TestSportmonksConnectionAction)->handle();
    }

    public function testOpenweather(): JsonResponse
    {
        return (new TestOpenweatherConnectionAction)->handle();
    }

    public function testWhatsapp(): JsonResponse
    {
        return (new TestWhatsappConnectionAction)->handle();
    }

    public function mediaStorage(): JsonResponse
    {
        return (new ShowMediaStorageStatusAction)->handle();
    }

    public function updateMediaStorage(UpdateMediaStorageSettingsRequest $request): JsonResponse
    {
        return (new UpdateMediaStorageSettingsAction)->handle($request->validated());
    }

    public function testMediaStorage(TestRemoteStorageRequest $request): JsonResponse
    {
        return (new TestRemoteStorageConnectionAction)->handle($request->validated());
    }

    public function syncMediaStorage(): JsonResponse
    {
        return (new SyncRemoteMediaNowAction)->handle();
    }
}
