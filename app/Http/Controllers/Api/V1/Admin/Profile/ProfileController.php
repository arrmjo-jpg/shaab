<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Profile;

use App\Actions\Admin\Profile\ChangePasswordAction;
use App\Actions\Admin\Profile\ListProfileActivityAction;
use App\Actions\Admin\Profile\ListSessionsAction;
use App\Actions\Admin\Profile\ProfileAnalyticsAction;
use App\Actions\Admin\Profile\ProfilePermissionsAction;
use App\Actions\Admin\Profile\ProfileSecurityAction;
use App\Actions\Admin\Profile\RevokeOtherSessionsAction;
use App\Actions\Admin\Profile\RevokeSessionAction;
use App\Actions\Admin\Profile\ShowProfileAction;
use App\Actions\Admin\Profile\UpdateProfileAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Profile\ChangePasswordRequest;
use App\Http\Requests\Admin\Profile\UpdateProfileRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    private function currentTokenId(Request $request): int|string|null
    {
        return $request->user()->currentAccessToken()?->getKey();
    }

    public function show(Request $request): JsonResponse
    {
        return (new ShowProfileAction)->handle($request->user());
    }

    public function update(UpdateProfileRequest $request): JsonResponse
    {
        return (new UpdateProfileAction)->handle($request->user(), $request->validated());
    }

    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        return (new ChangePasswordAction)->handle(
            $request->user(),
            $request->validated(),
            $this->currentTokenId($request)
        );
    }

    public function activity(Request $request): JsonResponse
    {
        return (new ListProfileActivityAction)->handle($request->user());
    }

    public function analytics(Request $request): JsonResponse
    {
        return (new ProfileAnalyticsAction)->handle($request->user());
    }

    public function permissions(Request $request): JsonResponse
    {
        return (new ProfilePermissionsAction)->handle($request->user());
    }

    public function security(Request $request): JsonResponse
    {
        return (new ProfileSecurityAction)->handle($request->user());
    }

    public function sessions(Request $request): JsonResponse
    {
        return (new ListSessionsAction)->handle(
            $request->user(),
            $this->currentTokenId($request)
        );
    }

    public function revokeSession(Request $request, int $id): JsonResponse
    {
        return (new RevokeSessionAction)->handle(
            $request->user(),
            $id,
            $this->currentTokenId($request)
        );
    }

    public function revokeOtherSessions(Request $request): JsonResponse
    {
        return (new RevokeOtherSessionsAction)->handle(
            $request->user(),
            $this->currentTokenId($request)
        );
    }
}
