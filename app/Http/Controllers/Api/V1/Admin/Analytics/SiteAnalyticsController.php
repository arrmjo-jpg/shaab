<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Analytics;

use App\Actions\Admin\Analytics\SiteAnalyticsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SiteAnalyticsController extends Controller
{
    /** لوحة تحليلات الموقع الموحّدة (قراءة-فقط) — analytics.view. */
    public function index(): JsonResponse
    {
        return (new SiteAnalyticsAction)->handle();
    }
}
