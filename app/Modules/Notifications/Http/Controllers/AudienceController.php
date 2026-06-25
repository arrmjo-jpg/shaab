<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Notifications\Audiences\AudienceResolverRegistry;
use App\Modules\Notifications\Enums\AudienceType;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * الجماهير — **أنواع مُعرَّفة في الكود** (AudienceResolverRegistry) هي SoT لـv1 (لا CRUD لقاعدة بيانات:
 * جدولا notification_audiences/segments سقالة غير موصولة، مؤجّلة). index يسرد المتاح؛ preview يَعُدّ
 * المستلمين حيًّا (userQuery للبريد/واتساب، deviceQuery للـpush) — تقدير قبل الإطلاق.
 */
final class AudienceController extends Controller
{
    public function index(AudienceResolverRegistry $registry): JsonResponse
    {
        $audiences = [];
        foreach (AudienceType::cases() as $type) {
            if (! $registry->has($type)) {
                continue; // نوع بلا resolver (team/writer/custom) — مؤجّل
            }
            $audiences[] = [
                'key' => $type->value,
                'spec' => $registry->for($type)->describe([])->toArray(),
            ];
        }

        return ApiResponse::success(data: $audiences);
    }

    public function preview(Request $request, AudienceResolverRegistry $registry): JsonResponse
    {
        $type = AudienceType::tryFrom((string) $request->query('audience', ''));
        if ($type === null || ! $registry->has($type)) {
            return ApiResponse::error(message: 'جمهور غير معروف', status: 422);
        }

        $resolver = $registry->for($type);
        $params = $request->query('params');
        $spec = $resolver->describe(is_array($params) ? $params : []);

        // عدّ حيّ من الاستعلامات (بُعدان مختلفان: مستخدمون للبريد/واتساب، أجهزة للـpush — لا يُجمَعان).
        $users = $resolver->userQuery($spec)?->count() ?? 0;
        $devices = $resolver->deviceQuery($spec)?->count() ?? 0;

        return ApiResponse::success(data: [
            'audience' => $type->value,
            'users' => $users,
            'devices' => $devices,
        ]);
    }
}
