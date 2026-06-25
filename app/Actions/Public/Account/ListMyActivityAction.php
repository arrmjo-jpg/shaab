<?php

declare(strict_types=1);

namespace App\Actions\Public\Account;

use App\Enums\EngagementType;
use App\Http\Resources\Public\Content\PublicArticleListItemResource;
use App\Http\Resources\Public\Content\PublicReelResource;
use App\Http\Resources\Public\VideoLibrary\PublicVideoCardResource;
use App\Models\Article;
use App\Models\Engagement;
use App\Models\Reel;
use App\Models\User;
use App\Models\Video;
use App\Support\Engagement\EngageableResolver;
use App\Support\Responses\ApiResponse;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\JsonResponse;

/**
 * User Activity API (قراءة-فقط) — المصدر الموحّد لكلّ نشاط المستخدم، **إسقاط قراءة فوق جدول
 * engagements** (نفس مصدر الكتابة = SSoT)، لا نظام/جدول/موديل جديد ولا منطق مكرّر.
 *
 * عامّ بمعاملين:
 *   • activity     : liked|saved الآن (↦ EngagementType). قابل للتوسعة (history|continue) بإضافة
 *                    قيمة هنا + مصدرها — دون نقطة جديدة. (بيانات المشاهدة مؤجَّلة — telemetry §B.1.)
 *   • content_type : أيّ نوع في EngageableResolver (article|video|reel…)؛ غيابه = الكل.
 *
 * polymorphic: يصفّ تفاعلات المستخدم (الأحدث أوّلاً) **المتاحة عامّاً فقط** (published/viewable —
 * لا روابط ميّتة) ويعيد **الموارد العامّة الموجودة** لكلّ نوع. غير قابل للكاش (per-user).
 */
class ListMyActivityAction
{
    /** activity → نوع التفاعل في جدول engagements. */
    private const ACTIVITY = [
        'liked' => EngagementType::Like,
        'saved' => EngagementType::Favorite,
    ];

    public function handle(User $actor, string $activity, ?string $contentType): JsonResponse
    {
        $engagementType = self::ACTIVITY[$activity];

        /** @var array<int,class-string> $classes */
        $classes = $contentType !== null
            ? array_filter([EngageableResolver::classFor($contentType)])
            : [Article::class, Video::class, Reel::class];

        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $paginator = Engagement::query()
            ->where('user_id', $actor->id)
            ->where('type', $engagementType->value)
            // الظهور العامّ لكلّ نوع (دقّة الترقيم) — لا تفاعل ظاهر مع محتوى غير منشور/محذوف.
            ->whereHasMorph('engageable', $classes, function ($query, string $morph): void {
                match ($morph) {
                    Article::class => $query->published(),
                    Video::class => $query->viewable(),
                    Reel::class => $query->published(),
                    default => null,
                };
            })
            // eager-load مطابق للقوائم العامّة الموجودة لكلّ نوع (إلغاء N+1).
            ->with(['engageable' => function (MorphTo $morphTo): void {
                $morphTo->morphWith([
                    Article::class => [
                        'author:id,name,avatar,is_writer',
                        'primaryCategory:id,name,slug',
                        'mediaAssets' => fn ($q) => $q->wherePivot('collection', 'cover'),
                    ],
                    Video::class => ['mediaAsset', 'category', 'engagementCounter'],
                    Reel::class => ['mediaAsset', 'engagementCounter'],
                ]);
            }])
            ->latest()
            ->paginate($perPage)
            ->appends(request()->query());

        $data = [];
        foreach ($paginator->items() as $engagement) {
            $presented = $this->present($engagement);
            if ($presented !== null) {
                $data[] = $presented;
            }
        }

        return ApiResponse::success(
            data: $data,
            meta: ['pagination' => [
                'total' => $paginator->total(),
                'count' => $paginator->count(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'total_pages' => $paginator->lastPage(),
            ]],
        );
    }

    /** يربط الهدف العامّ بمورده العامّ الموجود (لا موديل خام، لا بطاقة جديدة). */
    private function present(Engagement $engagement): ?array
    {
        $target = $engagement->engageable;

        return match (true) {
            $target instanceof Article => [
                'content_type' => 'article',
                'item' => (new PublicArticleListItemResource($target))->resolve(),
            ],
            $target instanceof Video => [
                'content_type' => 'video',
                'item' => (new PublicVideoCardResource($target))->resolve(),
            ],
            $target instanceof Reel => [
                'content_type' => 'reel',
                'item' => (new PublicReelResource($target))->resolve(),
            ],
            default => null,
        };
    }
}
