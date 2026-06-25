<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\PageResource;
use App\Models\Page;
use App\Models\PageUrlHistory;
use App\Models\User;
use App\Support\Cache\PageCacheTags;
use App\Support\Content\PageCdnPurge;
use App\Support\Content\PageContentSanitizer;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class UpdatePageAction
{
    /** الحقول القابلة للتعديل المباشر (الحالة عبر مسار الانتقال فقط). */
    private const FIELDS = [
        'author_id', 'locale', 'title', 'seo_title', 'seo_description',
        'seo_keywords', 'canonical_url', 'robots', 'template',
        'show_in_header', 'show_in_footer', 'sort_order',
    ];

    public function handle(Page $page, array $validated, User $actor): JsonResponse
    {
        // التُقط قبل التعديل لإبطال كاش/حافة الـ slug/اللغة القديمة عند تغيّرهما.
        $oldLocale = $page->locale;
        $oldSlug = (string) $page->slug;
        $oldPath = $page->canonicalPath();

        $wasPublished = $page->published_at !== null;

        $page = DB::transaction(function () use ($page, $validated, $oldLocale, $oldPath, $wasPublished): Page {
            foreach (self::FIELDS as $field) {
                if (array_key_exists($field, $validated)) {
                    $page->{$field} = $validated[$field];
                }
            }

            if (array_key_exists('content', $validated)) {
                $page->content = PageContentSanitizer::sanitize($validated['content']);
            }

            if (! empty($validated['slug'])) {
                $page->slug = $validated['slug'];
            }

            $page->save();

            // التقط المسار القانوني القديم عند تغيّره (لإعادة توجيه 301) — للصفحات
            // التي سبق نشرها فقط: الـ slugs المسوّدة لم تُعرَض للعامّة، فلا قيمة SEO
            // لحفظها. firstOrCreate يمنع التكرار عبر القيد الفريد (locale, old_path).
            $newPath = $page->fresh()->canonicalPath();
            if ($newPath !== $oldPath && $wasPublished) {
                PageUrlHistory::firstOrCreate(
                    ['locale' => $oldLocale, 'old_path' => $oldPath],
                    ['page_id' => $page->id, 'reason' => 'canonical_change'],
                );
            }

            return $page;
        });

        Cache::tags(PageCacheTags::invalidationTags($page, $oldLocale, $oldSlug))->flush();
        PageCdnPurge::purge($page, $oldPath);

        return ApiResponse::success(
            __('page.updated'),
            new PageResource($page->fresh()->load('author:id,name'))
        );
    }
}
