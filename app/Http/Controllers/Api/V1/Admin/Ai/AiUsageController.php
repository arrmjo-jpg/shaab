<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Ai;

use App\Actions\Admin\Ai\ShowAiUsageAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * رؤية استخدام الذكاء الاصطناعي للمشرف — قراءة فقط. التفويض ai.settings (مَن
 * يضبط الحدود يرى الاستهلاك). لا يكشف أي محتوى حسّاس (نصوص/تلقينات/مخرجات).
 */
class AiUsageController extends Controller
{
    public function index(): JsonResponse
    {
        return (new ShowAiUsageAction)->handle();
    }
}
