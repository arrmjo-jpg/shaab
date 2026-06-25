<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use App\Enums\WpCategoryMode;
use App\Models\Category;
use App\Models\MigrationCategoryMap;
use App\Models\MigrationRun;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;

/**
 * يبني معاينة الأثر (للقراءة فقط) من تنسيب التصنيفات المحفوظ + مصدر ووردبريس:
 *  - عدّ المنشورات الفريدة (بلا تكرار عبر تصنيفات متعدّدة).
 *  - التعارض = منشور في تصنيفات مختارة بأنواع مختلفة (news ↔ articles).
 *  - تقدير وسائط مُزال-التكرار (صور بارزة distinct؛ inline يُزال تكراره عند الاستيراد).
 *  - عيّنات تحويل تمثيلية (مصدر → هدف) لثقة المُشغِّل.
 */
final class MigrationPreviewBuilder
{
    public function __construct(
        private readonly MigrationRun $run,
        private readonly Connection $db,
    ) {}

    public static function for(MigrationRun $run): self
    {
        return new self($run, WpSourceConnection::connection($run));
    }

    /** @return array<string,mixed> */
    public function build(): array
    {
        $included = $this->run->categoryMaps()->get()
            ->filter(fn (MigrationCategoryMap $m): bool => $m->mode->isIncluded());

        // term_id → term_taxonomy_id (من حقائق التدقيق).
        $ttidByTermId = [];
        foreach (data_get($this->run->source_facts, 'categories.items', []) as $c) {
            $ttidByTermId[(int) $c['term_id']] = (int) $c['term_taxonomy_id'];
        }

        // أسماء التصنيفات الهدف (للعيّنات).
        $targetNames = Category::query()
            ->whereKey($included->pluck('target_category_id')->filter()->all())
            ->pluck('name', 'id');

        $newsTtids = [];
        $articleTtids = [];
        $typeByTtid = [];
        $targetNameByTtid = [];
        foreach ($included as $m) {
            $ttid = $ttidByTermId[$m->wp_term_id] ?? null;
            if ($ttid === null) {
                continue;
            }
            if ($m->mode === WpCategoryMode::News) {
                $newsTtids[] = $ttid;
            } else {
                $articleTtids[] = $ttid;
            }
            $typeByTtid[$ttid] = $m->mode->value;
            $targetNameByTtid[$ttid] = $m->target_category_id !== null
                ? ($targetNames[$m->target_category_id] ?? null)
                : null;
        }

        $allTtids = array_merge($newsTtids, $articleTtids);
        if ($allTtids === []) {
            return $this->emptyPreview();
        }

        // قوائم مُضمّنة آمنة (أعداد صحيحة محكومة — لا حقن).
        $newsList = implode(',', $newsTtids ?: [0]);
        $articleList = implode(',', $articleTtids ?: [0]);
        $allList = implode(',', $allTtids);

        $totals = $this->totals($newsList, $articleList, $allList);
        $media = $this->media($allList);

        return [
            'generated_at' => now()->toIso8601String(),
            'totals' => $totals,
            'media' => $media,
            'seo' => ['mapped' => $this->seoMapped($allList)],
            'redirects' => ['estimated' => $totals['unique_posts']],
            'warnings' => $this->warnings($totals['conflicts'], $media),
            'samples' => $this->samples($allList, $typeByTtid, $targetNameByTtid),
        ];
    }

    /**
     * عدّ فريد + لكل-نوع + تعارض، عبر تجميع على المنشور (دفعة واحدة).
     *
     * @return array{unique_posts:int,news:int,articles:int,conflicts:int}
     */
    private function totals(string $newsList, string $articleList, string $allList): array
    {
        // أعمدة غير مؤهَّلة (object_id/term_taxonomy_id فريدة في الربط) — آمنة على اتصال
        // ذي بادئة: Laravel يبدّل اسم الـ alias في الأعمدة المُغلَّفة لا في SQL الخام (#prefix).
        $sub = $this->eligible($allList)
            ->groupBy('object_id')
            ->selectRaw("object_id, MAX(CASE WHEN term_taxonomy_id IN ($newsList) THEN 1 ELSE 0 END) AS has_news, MAX(CASE WHEN term_taxonomy_id IN ($articleList) THEN 1 ELSE 0 END) AS has_articles");

        $agg = $this->db->query()->fromSub($sub, 't')->selectRaw(
            'SUM(CASE WHEN has_news = 1 AND has_articles = 0 THEN 1 ELSE 0 END) AS news_only,'
            .' SUM(CASE WHEN has_news = 0 AND has_articles = 1 THEN 1 ELSE 0 END) AS articles_only,'
            .' SUM(CASE WHEN has_news = 1 AND has_articles = 1 THEN 1 ELSE 0 END) AS conflicts,'
            .' COUNT(*) AS total'
        )->first();

        return [
            'unique_posts' => (int) ($agg->total ?? 0),
            'news' => (int) ($agg->news_only ?? 0),
            'articles' => (int) ($agg->articles_only ?? 0),
            'conflicts' => (int) ($agg->conflicts ?? 0),
        ];
    }

    /** @return array<string,mixed> */
    private function media(string $allList): array
    {
        $featuredUnique = (int) $this->db->table('postmeta as pm')
            ->joinSub($this->eligibleDistinct($allList), 'e', 'e.object_id', '=', 'pm.post_id')
            ->where('pm.meta_key', '_thumbnail_id')
            ->distinct()
            ->count('pm.meta_value');

        $postsWithInline = (int) $this->db->table('posts as p')
            ->joinSub($this->eligibleDistinct($allList), 'e', 'e.object_id', '=', 'p.ID')
            ->where('p.post_content', 'like', '%<img%')
            ->count();

        return [
            'featured_unique' => $featuredUnique,
            'posts_with_inline' => $postsWithInline,
            // الصور البارزة مُزالة-التكرار بالمرفق؛ inline يُزال تكراره عند الاستيراد (sha-256).
            'deduped' => true,
        ];
    }

    private function seoMapped(string $allList): int
    {
        if (! $this->db->getSchemaBuilder()->hasTable('yoast_indexable')) {
            return 0;
        }

        return (int) $this->db->table('yoast_indexable as y')
            ->joinSub($this->eligibleDistinct($allList), 'e', 'e.object_id', '=', 'y.object_id')
            ->where('y.object_type', 'post')
            ->distinct()
            ->count('y.object_id');
    }

    /**
     * عيّنات تحويل تمثيلية (أحدث المنشورات المؤهَّلة) — مصدر → هدف.
     *
     * @param  array<int,string>  $typeByTtid
     * @param  array<int,?string>  $targetNameByTtid
     * @return array<int,array<string,mixed>>
     */
    private function samples(string $allList, array $typeByTtid, array $targetNameByTtid): array
    {
        $ids = $this->eligible($allList)
            ->distinct()
            ->orderByDesc('object_id')
            ->limit(5)
            ->pluck('object_id');

        $hasYoast = $this->db->getSchemaBuilder()->hasTable('yoast_indexable');
        $out = [];

        foreach ($ids as $pid) {
            $post = $this->db->table('posts')->where('ID', $pid)
                ->first(['ID', 'post_title', 'post_content', 'post_excerpt']);
            if ($post === null) {
                continue;
            }

            $ttids = $this->db->table('term_relationships')
                ->where('object_id', $pid)
                ->whereRaw("term_taxonomy_id IN ($allList)")
                ->pluck('term_taxonomy_id')
                ->all();

            $types = array_values(array_unique(array_filter(
                array_map(fn ($tt): ?string => $typeByTtid[(int) $tt] ?? null, $ttids)
            )));
            $isConflict = count($types) > 1;
            $rawType = $types[0] ?? 'news';

            $targets = array_values(array_unique(array_filter(
                array_map(fn ($tt): ?string => $targetNameByTtid[(int) $tt] ?? null, $ttids)
            )));

            $yoast = $hasYoast
                ? $this->db->table('yoast_indexable')->where('object_id', $pid)
                    ->where('object_type', 'post')->first(['title', 'description'])
                : null;

            $body = trim(strip_tags((string) ($post->post_excerpt ?: $post->post_content)));

            $out[] = [
                'source' => [
                    'id' => (int) $pid,
                    'title' => (string) $post->post_title,
                    'excerpt' => mb_substr($body, 0, 200),
                ],
                'target' => [
                    'type' => $isConflict ? 'conflict' : ($rawType === 'articles' ? 'opinion' : 'news'),
                    'is_conflict' => $isConflict,
                    'target_categories' => $targets,
                    'byline' => MigrationAuthor::name(),
                    'status' => 'published',
                    'seo_title' => $yoast?->title,
                    'seo_description' => $yoast?->description,
                ],
            ];
        }

        return $out;
    }

    /** @return array<int,string> */
    private function warnings(int $conflicts, array $media): array
    {
        $w = [];
        if ($conflicts > 0) {
            $w[] = 'conflicts';
        }
        if (data_get($this->run->source_facts, 'encoding.healthy') === false) {
            $w[] = 'encoding';
        }
        if (data_get($this->run->source_facts, 'media.uploads_readable') === false) {
            $w[] = 'uploads';
        }

        return $w;
    }

    /** باني أساس للمنشورات المنشورة المؤهَّلة (post_type=post, publish). */
    private function eligible(string $allList): Builder
    {
        return $this->db->table('term_relationships as tr')
            ->join('posts as p', 'p.ID', '=', 'tr.object_id')
            ->where('p.post_type', 'post')
            ->where('p.post_status', 'publish')
            ->whereRaw("term_taxonomy_id IN ($allList)");
    }

    private function eligibleDistinct(string $allList): Builder
    {
        return $this->eligible($allList)->distinct()->select('object_id');
    }

    /** @return array<string,mixed> */
    private function emptyPreview(): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'totals' => ['unique_posts' => 0, 'news' => 0, 'articles' => 0, 'conflicts' => 0],
            'media' => ['featured_unique' => 0, 'posts_with_inline' => 0, 'deduped' => true],
            'seo' => ['mapped' => 0],
            'redirects' => ['estimated' => 0],
            'warnings' => [],
            'samples' => [],
        ];
    }
}
