<?php

declare(strict_types=1);

namespace App\Support\WpMigration;

use App\Models\MigrationRun;
use Illuminate\Database\Connection;
use Throwable;

/**
 * فاحص مصدر ووردبريس — قراءة فقط. يتحقّق من الاتصال وصلاحية القراءة، يكتشف نسخة
 * ووردبريس وبادئة جداولها، ويجمع حقائق التدقيق (طور الاكتشاف/التخطيط). كل
 * الاستعلامات عبر باني الاستعلام (محمول: mysql للمصدر الحقيقي، sqlite للاختبار).
 */
final class WpSourceInspector
{
    /** جداول ووردبريس الأساسية المطلوبة لتأكيد النسخة. */
    private const REQUIRED = ['posts', 'postmeta', 'options', 'terms', 'term_taxonomy', 'term_relationships'];

    public function __construct(private readonly Connection $db) {}

    public static function for(MigrationRun $run): self
    {
        return new self(WpSourceConnection::connection($run));
    }

    /** اتصال حيّ + صلاحية قراءة (SELECT 1). */
    public function canConnect(): bool
    {
        try {
            $this->db->select('select 1 as ok');

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<int,string> أسماء كل الجداول (بالبادئة الفعلية). */
    public function tableNames(): array
    {
        return $this->db->getSchemaBuilder()->getTableListing();
    }

    /** يكتشف بادئة جداول ووردبريس (مثل 3b5qs_) — مستقلّ عن بادئة الاتصال. */
    public function detectPrefix(): ?string
    {
        // تطبيع مؤهّل المخطّط (sqlite: main.، mysql: db.) قبل المطابقة.
        $names = array_map(self::bareName(...), $this->tableNames());

        foreach ($names as $name) {
            if (! str_ends_with($name, 'options')) {
                continue;
            }
            $prefix = substr($name, 0, -strlen('options'));
            // تأكيد بوجود posts + term_taxonomy بنفس البادئة (تجنّب مطابقة زائفة).
            if (in_array($prefix.'posts', $names, true)
                && in_array($prefix.'term_taxonomy', $names, true)) {
                return $prefix;
            }
        }

        return null;
    }

    /** يزيل مؤهّل المخطّط (main./db.) من اسم الجدول إن وُجد. */
    private static function bareName(string $name): string
    {
        $pos = strrpos($name, '.');

        return $pos === false ? $name : substr($name, $pos + 1);
    }

    /** الجداول الأساسية موجودة + لقطة siteurl ⇒ نسخة ووردبريس حقيقية. */
    public function isWordpress(): bool
    {
        $schema = $this->db->getSchemaBuilder();
        foreach (self::REQUIRED as $table) {
            if (! $schema->hasTable($table)) {
                return false;
            }
        }

        try {
            return $this->db->table('options')->where('option_name', 'siteurl')->exists();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * تدقيق المصدر (للقراءة فقط) — مدخلات اختيار التصنيفات ومعاينة الأثر.
     *
     * @return array<string,mixed>
     */
    public function facts(): array
    {
        return [
            'scanned_at' => now()->toIso8601String(),
            'prefix' => $this->db->getTablePrefix(),
            'site' => $this->site(),
            'posts' => $this->postCounts(),
            'attachments' => $this->attachmentCounts(),
            'media' => ['featured_count' => $this->metaCount('_thumbnail_id')],
            'categories' => $this->categories(),
            'seo' => $this->seo(),
            'authors' => $this->authors(),
            'content' => $this->contentShape(),
            'encoding' => $this->encoding(),
        ];
    }

    /** @return array<string,?string> */
    private function site(): array
    {
        $get = fn (string $key): ?string => $this->db->table('options')
            ->where('option_name', $key)->value('option_value');

        return [
            'url' => $get('siteurl'),
            'name' => $get('blogname'),
            'language' => $get('WPLANG') ?: 'ar',
        ];
    }

    /** @return array<string,int> */
    private function postCounts(): array
    {
        $base = fn () => $this->db->table('posts')->where('post_type', 'post');

        $byStatus = $base()
            ->selectRaw('post_status, count(*) as c')
            ->groupBy('post_status')
            ->pluck('c', 'post_status');

        return [
            'published' => (int) ($byStatus['publish'] ?? 0),
            'draft' => (int) ($byStatus['draft'] ?? 0),
            'pending' => (int) ($byStatus['pending'] ?? 0),
            'private' => (int) ($byStatus['private'] ?? 0),
            'total' => (int) $base()->count(),
        ];
    }

    /** @return array<string,mixed> */
    private function attachmentCounts(): array
    {
        $byMime = $this->db->table('posts')->where('post_type', 'attachment')
            ->selectRaw('post_mime_type as mime, count(*) as c')
            ->groupBy('post_mime_type')
            ->orderByDesc('c')
            ->limit(12)
            ->get()
            ->map(fn ($r): array => ['mime' => (string) $r->mime, 'count' => (int) $r->c])
            ->all();

        return [
            'total' => (int) $this->db->table('posts')->where('post_type', 'attachment')->count(),
            'by_mime' => $byMime,
        ];
    }

    /**
     * تصنيفات المصدر مع عدّين صريحين (لا غموض):
     *  - count       : المنشورات المُسنَدة مباشرةً (term_taxonomy.count).
     *  - total_count : شامل الأبناء (مجموع الشجرة الفرعية) للأب الهرمي.
     *
     * @return array<string,mixed>
     */
    private function categories(): array
    {
        $rows = $this->db->table('term_taxonomy as tt')
            ->join('terms as t', 't.term_id', '=', 'tt.term_id')
            ->where('tt.taxonomy', 'category')
            ->get(['tt.term_taxonomy_id', 'tt.term_id', 't.name', 't.slug', 'tt.parent', 'tt.count']);

        // خرائط: العدّ المباشر لكل term، وأبناء كل term (parent يشير إلى term_id).
        $direct = [];
        $children = [];
        foreach ($rows as $r) {
            $direct[(int) $r->term_id] = (int) $r->count;
            $children[(int) $r->parent][] = (int) $r->term_id;
        }

        $totalOf = function (int $termId) use (&$totalOf, $direct, $children): int {
            $sum = $direct[$termId] ?? 0;
            foreach ($children[$termId] ?? [] as $childId) {
                $sum += $totalOf($childId);
            }

            return $sum;
        };

        $items = $rows
            ->map(fn ($r): array => [
                'term_taxonomy_id' => (int) $r->term_taxonomy_id,
                'term_id' => (int) $r->term_id,
                'name' => (string) $r->name,
                'slug' => rawurldecode((string) $r->slug),
                'parent' => (int) $r->parent,
                'count' => (int) $r->count,
                'total_count' => $totalOf((int) $r->term_id),
            ])
            ->sortByDesc('total_count')
            ->values()
            ->all();

        return ['count' => count($items), 'items' => $items];
    }

    /**
     * تحقّق ترميز عربي على عيّنة عناوين منشورة — كشف مشاكل الترميز قبل التنفيذ
     * (UTF-8 غير صالح / mojibake من قواعد latin1). تيليمتري الاكتشاف لا حسم.
     *
     * @return array<string,mixed>
     */
    private function encoding(): array
    {
        $titles = $this->db->table('posts')
            ->where('post_type', 'post')
            ->where('post_status', 'publish')
            ->orderByDesc('ID')
            ->limit(100)
            ->pluck('post_title');

        $invalidUtf8 = 0;
        $arabic = 0;
        $mojibake = 0;

        foreach ($titles as $title) {
            $s = (string) $title;

            if (! mb_check_encoding($s, 'UTF-8')) {
                $invalidUtf8++;

                continue;
            }

            $hasArabic = (bool) preg_match('/[\x{0600}-\x{06FF}]/u', $s);
            if ($hasArabic) {
                $arabic++;
            }
            // U+FFFD أو تتابع لاتيني ممدود بلا عربية ⇒ ترميز مشبوه.
            if (str_contains($s, "\u{FFFD}") || (! $hasArabic && preg_match('/[\x{00C0}-\x{00FF}]{2,}/u', $s))) {
                $mojibake++;
            }
        }

        return [
            'sampled' => $titles->count(),
            'invalid_utf8' => $invalidUtf8,
            'arabic_titles' => $arabic,
            'suspected_mojibake' => $mojibake,
            'healthy' => $invalidUtf8 === 0 && $mojibake === 0,
        ];
    }

    /** @return array<string,mixed> */
    private function seo(): array
    {
        $yoast = $this->hasTable('yoast_indexable');

        return [
            'provider' => $yoast ? 'yoast' : 'none',
            'yoast_indexable' => $yoast,
            'primary_category_meta' => $this->metaCount('_yoast_wpseo_primary_category'),
            'focus_keywords' => $this->metaCount('_yoast_wpseo_focuskw'),
        ];
    }

    /** @return array<string,int> */
    private function authors(): array
    {
        return [
            'guest_author_meta' => $this->metaCount('sfly_guest_author_names'),
            'wp_users' => $this->hasTable('users') ? (int) $this->db->table('users')->count() : 0,
        ];
    }

    /** @return array<string,int> */
    private function contentShape(): array
    {
        $base = fn () => $this->db->table('posts')->where('post_type', 'post');

        return [
            'gutenberg' => (int) $base()->where('post_content', 'like', '%<!-- wp:%')->count(),
            'with_inline_images' => (int) $base()->where('post_content', 'like', '%<img%')->count(),
            'subtitle_meta' => $this->metaCount('post_subtitle'),
        ];
    }

    private function metaCount(string $key): int
    {
        if (! $this->hasTable('postmeta')) {
            return 0;
        }

        return (int) $this->db->table('postmeta')->where('meta_key', $key)->count();
    }

    private function hasTable(string $bare): bool
    {
        return $this->db->getSchemaBuilder()->hasTable($bare);
    }
}
