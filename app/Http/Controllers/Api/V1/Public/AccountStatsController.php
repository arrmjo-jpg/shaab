<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public;

use App\Actions\Public\Account\ListMyStatsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AccountStatsController extends Controller
{
    /** إحصاءات لوحة المستخدم (قراءة-فقط) — للمستخدم المصادَق (ability=user). */
    public function index(Request $request): JsonResponse
    {
        return (new ListMyStatsAction)->handle($request->user());
    }
}
