<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\Account\ListMyActivityAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Public\Account\ListMyActivityRequest;
use Illuminate\Http\JsonResponse;

/**
 * User Activity API (قراءة-فقط) — نقطة موحّدة لنشاط المستخدم (أعجبني/المحفوظات الآن،
 * قابلة للتوسعة عبر معامل activity). موصِّل رفيع: يفوّض لـ ListMyActivityAction.
 */
class AccountActivityController extends Controller
{
    public function index(ListMyActivityRequest $request): JsonResponse
    {
        $validated = $request->validated();

        return (new ListMyActivityAction)->handle(
            $request->user(),
            $validated['activity'],
            $validated['content_type'] ?? null,
        );
    }
}
