<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Broadcast;

use App\Actions\Public\Broadcast\ListPublicBroadcastsAction;
use App\Actions\Public\Broadcast\ShowPublicBroadcastAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * واجهة البثّ العامة (الجوّال أولاً + الواجهة أولاً). النوع (kind) قطعةُ مسارٍ محصورة
 * بـ where (live|tv|radio) فلا يُلتقط كـ slug. كل النقاط داخل public.cache (CDN/ETag)
 * + throttle:public.read. عربي فقط — لا بادئة لغة (النطاق مستقلّ).
 */
class BroadcastController extends Controller
{
    public function index(Request $request, string $kind): JsonResponse
    {
        return (new ListPublicBroadcastsAction)->handle($kind, $request);
    }

    public function show(string $kind, string $slug): JsonResponse
    {
        return (new ShowPublicBroadcastAction)->handle($kind, $slug);
    }
}
