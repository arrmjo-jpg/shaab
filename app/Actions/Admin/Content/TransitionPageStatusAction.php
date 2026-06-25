<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Enums\PageStatus;
use App\Http\Resources\Admin\Content\PageResource;
use App\Models\Page;
use App\Models\User;
use App\Support\Cache\PageCacheTags;
use App\Support\Content\PageCdnPurge;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * انتقال حالة الصفحة (يدوي محكوم بالصلاحيات). البوابة الخشنة pages.edit على المسار؛
 * النشر/الأرشفة يتطلّبان صلاحية دقيقة إضافية تُفرَض هنا (لا policy منفصل).
 */
class TransitionPageStatusAction
{
    public function handle(Page $page, array $validated, User $actor): JsonResponse
    {
        $target = PageStatus::from($validated['status']);

        if ($denied = $this->guard($actor, $target)) {
            return $denied;
        }

        $page = DB::transaction(function () use ($page, $actor, $target): Page {
            $page->status = $target->value;

            if ($target === PageStatus::Published) {
                $page->published_at = $page->published_at ?? now();
                $page->published_by_id = $actor->id;
            }

            $page->save();

            return $page;
        });

        Cache::tags(PageCacheTags::invalidationTags($page))->flush();
        PageCdnPurge::purge($page);

        return ApiResponse::success(
            __('page.status_changed'),
            new PageResource($page->fresh()->load('author:id,name'))
        );
    }

    /** النشر يتطلّب pages.publish، والأرشفة pages.archive (إضافةً لـ pages.edit). */
    private function guard(User $actor, PageStatus $target): ?JsonResponse
    {
        $ability = match ($target) {
            PageStatus::Published => 'pages.publish',
            PageStatus::Archived => 'pages.archive',
            default => null,
        };

        if ($ability !== null && ! $actor->can($ability)) {
            return ApiResponse::error(__('page.forbidden_transition'), [], 403);
        }

        return null;
    }
}
