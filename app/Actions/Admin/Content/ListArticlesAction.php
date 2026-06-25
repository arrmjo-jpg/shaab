<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\ArticleResource;
use App\Models\Article;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * قائمة المقالات (لوحة الإدارة) — مرقّمة عبر QueryBuilder.
 *
 * Wave C2: لا كاش — قائمة إدارية متغيّرة وقابلة للفلترة، ولا قراءة عامة
 * في هذه الموجة. كاش القراءة العامة/الصفحة الرئيسية يأتي في موجاته.
 */
class ListArticlesAction
{
    public function handle(): JsonResponse
    {
        $default = (int) config('performance.pagination.default');
        $max = (int) config('performance.pagination.max');
        $perPage = max(1, min((int) request()->integer('per_page', $default), $max));

        $query = QueryBuilder::for(Article::class)
            ->with([
                'author:id,name',
                'primaryCategory:id,name,slug',
                'categories:id,name,slug',
                'engagementCounter',
            ])
            ->allowedFilters(
                AllowedFilter::exact('type'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('locale'),
                AllowedFilter::exact('primary_category_id'),
                // فلتر القسم الموحّد: يطابق المقال إن كان القسم رئيسياً أو ثانوياً
                // (pivot) — فالخبر متعدّد الأقسام يظهر تحت كل أقسامه، لا الرئيسي فقط.
                AllowedFilter::callback('category', function ($query, $value): void {
                    $query->where(function ($q) use ($value): void {
                        $q->where('primary_category_id', $value)
                            ->orWhereHas('categories', fn ($c) => $c->where('categories.id', $value));
                    });
                }),
                AllowedFilter::exact('is_featured'),
                AllowedFilter::exact('is_breaking'),
                AllowedFilter::exact('is_pinned'),
                AllowedFilter::exact('is_header'),
                AllowedFilter::exact('is_editor_pick'),
                // بحث العنوان عبر FULLTEXT (ngram) على MySQL — مفهرس، لا مسح كامل.
                // fallback إلى LIKE على محرّكات بلا FULLTEXT (SQLite في الاختبارات).
                AllowedFilter::callback('title', function ($query, $value): void {
                    $term = trim((string) $value);
                    if ($term === '') {
                        return;
                    }
                    if (DB::connection()->getDriverName() === 'mysql') {
                        $query->whereFullText('title', $term);
                    } else {
                        $query->where('title', 'like', '%'.$term.'%');
                    }
                }),
            )
            ->allowedSorts('id', 'title', 'created_at', 'published_at')
            ->defaultSort('-created_at');

        // عرض المحذوفات: only=المحذوفة فقط، with=الكل (تشمل المحذوف).
        $trashed = (string) request()->query('trashed', '');
        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        $articles = $query->paginate($perPage)->appends(request()->query());

        return ApiResponse::success(
            data: ArticleResource::collection($articles)->resolve(),
            meta: [
                'pagination' => [
                    'total' => $articles->total(),
                    'count' => $articles->count(),
                    'per_page' => $articles->perPage(),
                    'current_page' => $articles->currentPage(),
                    'total_pages' => $articles->lastPage(),
                ],
            ]
        );
    }
}
