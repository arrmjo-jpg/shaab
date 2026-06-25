<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\System;

use App\Actions\Admin\System\ClearContentCacheAction;
use App\Actions\Admin\System\SystemDiagnosticsAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * عمليات النظام التشغيلية — تشخيص آمن (قراءة) + تفريغ كاش المحتوى (استرداد).
 * التفويض على مستوى المسار: التشخيص يتطلّب scheduler.view، والتفريغ cache.clear.
 */
class SystemController extends Controller
{
    public function diagnostics(): JsonResponse
    {
        return (new SystemDiagnosticsAction)->handle();
    }

    public function clearCache(): JsonResponse
    {
        return (new ClearContentCacheAction)->handle();
    }
}
