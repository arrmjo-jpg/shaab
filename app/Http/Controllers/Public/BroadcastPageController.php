<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Enums\BroadcastKind;
use App\Http\Controllers\Controller;
use App\Models\Broadcast;
use App\Support\Content\BroadcastSeoBuilder;
use Illuminate\Contracts\View\View;

/**
 * السطح العام للبثّ — صفحات SSR بـ Blade (لا SPA). يستهلك سكوبات النموذج القائمة
 * (publiclyListed / publiclyVisible) ولا يُعيد اشتقاق منطق الرؤية. النوع (kind)
 * قطعةُ مسارٍ محصورة بـ where(live|tv|radio) فلا يصطدم بـ / أو robots/sitemap.
 *
 * عربي فقط، RTL — لا بادئة لغة (النطاق مستقلّ، مرآة canonicalPath = /{kind}/{slug}).
 * التفاعل الحيّ (مشاهدون/تفاعل/تذكير/مشغّل) يُروى تدريجياً عبر resources/js/broadcast.js
 * من data-attributes؛ كل ما هو مهمّ للزواحف يُرسَم خادمياً.
 */
class BroadcastPageController extends Controller
{
    private const PER_PAGE = 24;

    public function index(string $kind): View
    {
        // المسار محصور بالفعل على live|tv|radio؛ from() آمن.
        $kindEnum = BroadcastKind::from($kind);

        $broadcasts = Broadcast::query()
            ->publiclyListed($kind)
            ->with(['category', 'engagementCounter', 'cover'])
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('broadcast.index', [
            'kind' => $kindEnum,
            'broadcasts' => $broadcasts,
        ]);
    }

    public function show(string $kind, string $slug): View
    {
        $kindEnum = BroadcastKind::from($kind);

        $broadcast = Broadcast::query()
            ->publiclyVisible()
            ->ofKind($kind)
            ->where('slug', $slug)
            ->with([
                'category',
                'engagementCounter',
                'cover',
                'vodVideo' => fn ($q) => $q->public()->playable(),
            ])
            ->first();

        abort_if($broadcast === null, 404);

        return view('broadcast.show', [
            'kind' => $kindEnum,
            'broadcast' => $broadcast,
            'seo' => BroadcastSeoBuilder::build($broadcast),
        ]);
    }
}
