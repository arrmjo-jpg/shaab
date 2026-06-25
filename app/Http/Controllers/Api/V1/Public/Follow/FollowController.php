<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Follow;

use App\Actions\Public\Follow\ListFollowsAction;
use App\Actions\Public\Follow\ToggleFollowAction;
use App\Enums\FollowableType;
use App\Http\Controllers\Controller;
use App\Models\Follow;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * نظام «تابع» — متابعة كيانات 365 (فريق/بطولة/لاعب/مباراة). كلّه يتطلّب مستخدماً مُصادَقاً
 * (المسار محميّ auth:sanctum). الهدف من المسار (type/id) كنمط التفاعل. الحالة per-user (no-store).
 */
class FollowController extends Controller
{
    /** هل يتابع المستخدمُ هذا الكيان؟ (لترطيب الزرّ client-side فوق صفحة مُكاشة). */
    public function state(Request $request, string $type, int $id): JsonResponse
    {
        $ft = FollowableType::tryFrom($type);
        if ($ft === null) {
            return ApiResponse::error(__('follow.unsupported_type'), [], 422);
        }

        $following = Follow::query()
            ->forUser($request->user()->id)
            ->ofType($ft)
            ->where('followable_id', $id)
            ->exists();

        return ApiResponse::success(data: ['following' => $following]);
    }

    public function toggle(Request $request, string $type, int $id): JsonResponse
    {
        $ft = FollowableType::tryFrom($type);
        if ($ft === null) {
            return ApiResponse::error(__('follow.unsupported_type'), [], 422);
        }

        return (new ToggleFollowAction)->handle($request->user(), $ft, $id);
    }

    /** قائمة «أتابعهم» (تصفية اختياريّة ?type=). */
    public function index(Request $request): JsonResponse
    {
        $typeParam = $request->query('type');
        $ft = is_string($typeParam) ? FollowableType::tryFrom($typeParam) : null;

        return (new ListFollowsAction)->handle($request->user(), $ft);
    }
}
