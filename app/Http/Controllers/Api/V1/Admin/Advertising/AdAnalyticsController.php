<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Advertising;

use App\Actions\Admin\Advertising\AdAnalyticsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * تحليلات الإعلانات (تجميعيّة) — قراءة فقط خلف permission:ads.view. النطاق الزمنيّ عبر
 * range/from/to (مرآة اتفاقية التحليلات). كل المنطق في AdAnalyticsAction.
 */
class AdAnalyticsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return (new AdAnalyticsAction)->handle($request);
    }
}
