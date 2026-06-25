<?php

declare(strict_types=1);

namespace App\Support\Ai;

/**
 * بدائل تحريرية حتمية بلا ذكاء اصطناعي — تُستخدَم عند تعطّل/إيقاف المزوّد،
 * فلا يبقى المحرّر بلا ملخّص أو تقييم سيو. تشتقّ كل شيء من العنوان + نصّ المقال.
 *
 * عملية بحتة (لا I/O، لا حالة) — قابلة للاختبار مباشرة.
 */
final class EditorialHeuristics
{
    private const EXCERPT_MAX = 220;

    private const TITLE_MIN = 30;

    private const TITLE_MAX = 65;

    private const DESC_MIN = 120;

    private const DESC_MAX = 160;

    /**
     * كلمات وظيفية شائعة (عربية + إنجليزية) تُستبعَد من الوسوم المشتقّة
     * تلقائياً، فلا تتسرّب أدوات الربط والضمائر إلى الاقتراحات.
     *
     * @var array<int,string>
     */
    private const STOPWORDS = [
        // عربية شائعة
        'من', 'في', 'على', 'إلى', 'عن', 'مع', 'هذا', 'هذه', 'ذلك', 'التي', 'الذي',
        'الذين', 'كان', 'كانت', 'وقد', 'قد', 'لقد', 'كما', 'حيث', 'بعد', 'قبل',
        'عند', 'لدى', 'بين', 'خلال', 'حول', 'أو', 'أم', 'ثم', 'إذ', 'إذا', 'لكن',
        'كل', 'بعض', 'غير', 'سوف', 'لم', 'لن', 'ما', 'لا', 'إن', 'أن', 'أنّ',
        'وهو', 'وهي', 'هو', 'هي', 'هم', 'هن', 'نحن', 'أنت', 'أنا', 'به', 'بها',
        'له', 'لها', 'فيه', 'فيها', 'عليه', 'عليها', 'إليه', 'إليها', 'الى',
        'منذ', 'حتى', 'دون', 'ضد', 'نحو', 'عبر', 'وفي', 'ومن', 'وعلى', 'وكان',
        // إنجليزية شائعة
        'the', 'and', 'for', 'with', 'that', 'this', 'from', 'are', 'was', 'were',
        'has', 'have', 'had', 'will', 'would', 'about', 'into', 'over', 'after',
        'before', 'between', 'their', 'they', 'them', 'which', 'while', 'been',
    ];

    /** ملخّص قصير من أوّل جُمل المتن، أو العنوان عند غياب المتن. */
    public function excerpt(string $title, string $body): string
    {
        $body = trim(preg_replace('/\s+/u', ' ', $body) ?? '');

        if ($body === '') {
            return trim($title);
        }

        // تقسيم على نهايات الجُمل (نقطة/تعجّب/استفهام عربي وإنجليزي).
        $sentences = preg_split('/(?<=[.!؟?])\s+/u', $body) ?: [$body];

        $out = '';
        foreach ($sentences as $sentence) {
            $candidate = trim($out.' '.$sentence);
            if ($out !== '' && mb_strlen($candidate) > self::EXCERPT_MAX) {
                break;
            }
            $out = $candidate;
            if (mb_strlen($out) >= self::EXCERPT_MAX) {
                break;
            }
        }

        if (mb_strlen($out) > self::EXCERPT_MAX) {
            $out = rtrim(mb_substr($out, 0, self::EXCERPT_MAX)).'…';
        }

        return $out;
    }

    /**
     * تقييم سيو قائم على قواعد — نفس بنية ناتج المساعد الذكي.
     *
     * @param  array<string,mixed>  $payload  title, excerpt, slug, tags, body
     * @return array{score:int,title_feedback:string,description_feedback:string,missing_keywords:array<int,string>,suggestions:array<int,string>}
     */
    public function seo(array $payload): array
    {
        $title = trim((string) ($payload['title'] ?? ''));
        $excerpt = trim((string) ($payload['excerpt'] ?? ''));
        $slug = trim((string) ($payload['slug'] ?? ''));
        $body = trim((string) ($payload['body'] ?? ''));
        /** @var array<int,string> $tags */
        $tags = array_values(array_filter(array_map(
            fn ($t): string => trim((string) $t),
            is_array($payload['tags'] ?? null) ? $payload['tags'] : []
        )));

        // الوصف الفعّال: المقتطف، أو أوّل المتن إن غاب.
        $description = $excerpt !== '' ? $excerpt : $this->excerpt($title, $body);

        $titleLen = mb_strlen($title);
        $descLen = mb_strlen($description);

        $score = 100;
        $suggestions = [];

        // العنوان
        if ($title === '') {
            $titleFeedback = __('ai.heuristic.title_missing');
            $score -= 35;
            $suggestions[] = __('ai.heuristic.sg_add_title');
        } elseif ($titleLen < self::TITLE_MIN) {
            $titleFeedback = __('ai.heuristic.title_short', ['len' => $titleLen]);
            $score -= 12;
        } elseif ($titleLen > self::TITLE_MAX) {
            $titleFeedback = __('ai.heuristic.title_long', ['len' => $titleLen]);
            $score -= 10;
            $suggestions[] = __('ai.heuristic.sg_shorten_title');
        } else {
            $titleFeedback = __('ai.heuristic.title_ok', ['len' => $titleLen]);
        }

        // الوصف
        if ($description === '') {
            $descFeedback = __('ai.heuristic.desc_missing');
            $score -= 30;
            $suggestions[] = __('ai.heuristic.sg_add_description');
        } elseif ($descLen < self::DESC_MIN) {
            $descFeedback = __('ai.heuristic.desc_short', ['len' => $descLen]);
            $score -= 10;
            $suggestions[] = __('ai.heuristic.sg_lengthen_description');
        } elseif ($descLen > self::DESC_MAX) {
            $descFeedback = __('ai.heuristic.desc_long', ['len' => $descLen]);
            $score -= 6;
        } else {
            $descFeedback = __('ai.heuristic.desc_ok', ['len' => $descLen]);
        }

        // الـ slug
        if ($slug === '') {
            $score -= 8;
            $suggestions[] = __('ai.heuristic.sg_add_slug');
        }

        // الوسوم
        if ($tags === []) {
            $score -= 10;
            $suggestions[] = __('ai.heuristic.sg_add_tags');
        }

        // كلمات مفتاحية مقترحة: كلمات بارزة من العنوان غير المغطّاة بالوسوم.
        $missing = $this->missingKeywords($title.' '.$body, $tags);
        if ($missing !== [] && $tags !== []) {
            $suggestions[] = __('ai.heuristic.sg_cover_keywords');
        }

        return [
            'score' => max(0, min(100, $score)),
            'title_feedback' => $titleFeedback,
            'description_feedback' => $descFeedback,
            'missing_keywords' => $missing,
            'suggestions' => array_values(array_unique($suggestions)),
        ];
    }

    /**
     * توليد وسوم حتمي واعٍ بالعربية — بديل دائم التوفّر عند تعطّل/إيقاف الذكاء
     * الاصطناعي. يشتقّ «المواضيع» من تكرار الكلمات البارزة (مع ترجيح العنوان
     * والعنوان الفرعي)، بعد استبعاد الكلمات الوظيفية. لا يستنتج أشخاصاً/أماكن/
     * منظمات (يتطلّب تحليلاً صرفياً) فيتركها فارغة بنفس بنية ناتج الذكاء.
     *
     * @param  array<string,mixed>  $context  title, subtitle, body
     * @return array{people:array<int,string>,locations:array<int,string>,organizations:array<int,string>,topics:array<int,string>}
     */
    public function tags(array $context): array
    {
        $title = trim((string) ($context['title'] ?? ''));
        $subtitle = trim((string) ($context['subtitle'] ?? ''));
        $body = trim((string) ($context['body'] ?? ''));

        // ترجيح: كلمات العنوان أهمّ من الفرعي، والفرعي أهمّ من المتن.
        $weighted = [
            3 => $title,
            2 => $subtitle,
            1 => mb_substr($body, 0, 6000),
        ];

        /** @var array<string,int> $freq تكرار مُرجَّح حسب أوّل ظهور للسطح */
        $freq = [];
        /** @var array<string,string> $surface صيغة السطح الأولى لكل مفتاح */
        $surface = [];

        foreach ($weighted as $weight => $text) {
            if ($text === '') {
                continue;
            }
            foreach ($this->tokenize($text) as $word) {
                $key = mb_strtolower($word);
                if (mb_strlen($key) <= 2 || in_array($key, self::STOPWORDS, true)) {
                    continue;
                }
                if (! isset($surface[$key])) {
                    $surface[$key] = $word;
                }
                $freq[$key] = ($freq[$key] ?? 0) + $weight;
            }
        }

        arsort($freq);

        $topics = [];
        foreach (array_keys($freq) as $key) {
            $topics[] = $surface[$key];
            if (count($topics) >= 10) {
                break;
            }
        }

        return [
            'people' => [],
            'locations' => [],
            'organizations' => [],
            'topics' => $topics,
        ];
    }

    /**
     * كلمات محتوى بارزة (>3 أحرف) غير موجودة ضمن الوسوم — حتى 5.
     *
     * @param  array<int,string>  $tags
     * @return array<int,string>
     */
    private function missingKeywords(string $text, array $tags): array
    {
        $tagsLower = array_map('mb_strtolower', $tags);
        $words = preg_split('/[\s,،.؟!?:؛"«»()\-]+/u', mb_strtolower($text)) ?: [];

        $freq = [];
        foreach ($words as $w) {
            if (mb_strlen($w) <= 3) {
                continue;
            }
            if (in_array($w, $tagsLower, true)) {
                continue;
            }
            $freq[$w] = ($freq[$w] ?? 0) + 1;
        }

        arsort($freq);

        return array_slice(array_keys($freq), 0, 5);
    }

    /**
     * تقطيع نصّ إلى كلمات واعٍ بالترقيم العربي والإنجليزي.
     *
     * @return array<int,string>
     */
    private function tokenize(string $text): array
    {
        $words = preg_split('/[\s,،.؟!?:؛"«»“”‘’()\[\]{}\-—–\/\\\\|]+/u', $text) ?: [];

        return array_values(array_filter($words, static fn (string $w): bool => $w !== ''));
    }
}
