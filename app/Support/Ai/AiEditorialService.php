<?php

declare(strict_types=1);

namespace App\Support\Ai;

use App\Contracts\Ai\AiProvider;
use App\Settings\ThirdPartySettings;
use RuntimeException;

/**
 * المساعد التحريري — منطق الذكاء الاصطناعي المُحايد للمزوّد.
 *
 * يبني التلقينات (prompts) ويحلّل الاستجابة المُهيكلة. يعتمد على عقد AiProvider
 * فقط، فلا يعرف ولا يهتم بأي مزوّد فعلي يُنفّذ النداء (لا قفل على مزوّد).
 *
 * كل العمليات «مساعدة تحريرية»: اقتراحات يستعرضها الصحفي ويطبّقها يدوياً —
 * لا توليد تلقائي ولا حفظ ضمنيّ.
 */
final class AiEditorialService
{
    /** أنماط إعادة الصياغة المسموح بها. */
    public const REWRITE_MODES = [
        'journalistic', 'formal', 'concise', 'stronger', 'simplified', 'professional', 'seo',
    ];

    public function __construct(
        private readonly AiProvider $provider,
        private readonly ThirdPartySettings $settings,
    ) {}

    public function provider(): AiProvider
    {
        return $this->provider;
    }

    /** هل المساعد مُفعَّل في اللوحة ومزوّده مُهيّأ بمفتاح؟ */
    public function available(): bool
    {
        return $this->settings->ai_enabled && $this->provider->configured();
    }

    /**
     * اقتراحات عناوين مجمّعة: 5 إخبارية + 3 تحريرية قوية + 3 صديقة للسيو.
     *
     * @param  array<string,mixed>  $context
     * @return array{news:array<int,string>,editorial:array<int,string>,seo:array<int,string>}
     */
    public function suggestHeadlines(array $context): array
    {
        $data = $this->chatJson([
            $this->systemMessage(
                'أنت محرّر عناوين في غرفة أخبار عربية محترفة. تكتب عناوين دقيقة وجذّابة '
                .'وغير مضلِّلة، تحترم لغة الخبر وسياقه.'
            ),
            [
                'role' => 'user',
                'content' => "اقترح عناوين للمحتوى التالي.\n\n"
                    .$this->contextBlock($context)
                    ."\n\nكل عنوان نصّ صِرف: بلا ترقيم ولا تعداد ولا علامات اقتباس ولا رموز Markdown."
                    ."\nأعِد JSON فقط بالشكل:\n"
                    .'{"news": ["..خمسة عناوين إخبارية مباشرة.."], '
                    .'"editorial": ["..ثلاثة عناوين تحريرية قوية.."], '
                    .'"seo": ["..ثلاثة عناوين صديقة لمحركات البحث (≤60 حرفاً، تتضمّن كلمة مفتاحية).."]}',
            ],
        ]);

        return [
            'news' => $this->stringList($data['news'] ?? [], 5),
            'editorial' => $this->stringList($data['editorial'] ?? [], 3),
            'seo' => $this->stringList($data['seo'] ?? [], 3),
        ];
    }

    /**
     * ملخّص/مقتطف تحريري قصير عالي الجودة.
     *
     * @param  array<string,mixed>  $context
     */
    public function generateExcerpt(array $context): string
    {
        $data = $this->chatJson([
            $this->systemMessage(
                'أنت محرّر في غرفة أخبار عربية. تكتب مقتطفاً تحريرياً موجزاً (جملة إلى جملتين، '
                .'≤300 حرف) يلخّص جوهر الخبر بأسلوب صحفي نظيف، دون مبالغة أو حشو.'
            ),
            [
                'role' => 'user',
                'content' => "ولّد مقتطفاً للمحتوى التالي.\n\n"
                    .$this->contextBlock($context)
                    ."\n\nأعِد JSON فقط: {\"excerpt\": \"..\"}",
            ],
        ]);

        return trim((string) ($data['excerpt'] ?? ''));
    }

    /**
     * إعادة صياغة نصّ مُحدَّد وفق نمط — تُطبَّق على المقطع المختار فقط.
     */
    public function rewrite(string $text, string $mode, string $locale = 'ar'): string
    {
        if (! in_array($mode, self::REWRITE_MODES, true)) {
            throw new RuntimeException('ai_invalid_rewrite_mode');
        }

        $instructions = [
            'journalistic' => 'بأسلوب صحفي خبري واضح ومباشر',
            'formal' => 'بأسلوب رسمي رصين',
            'concise' => 'بصياغة مختصرة وموجزة دون فقدان المعنى',
            'stronger' => 'بصياغة أقوى وأكثر تأثيراً وجاذبية',
            'simplified' => 'بأسلوب مبسّط يسهل فهمه على القارئ العام',
            'professional' => 'بأسلوب احترافي متقَن يليق بمنصّة إعلامية مرموقة',
            'seo' => 'بصياغة صديقة لمحرّكات البحث تُبرز الكلمات المفتاحية دون حشو',
        ];

        $langLabel = $locale === 'en' ? 'الإنجليزية' : 'العربية';

        $data = $this->chatJson([
            $this->systemMessage(
                'أنت محرّر لغوي محترف. تعيد صياغة المقطع المُعطى فقط، مع الحفاظ على المعنى '
                .'واللغة، دون إضافة معلومات جديدة ودون شرح.'
            ),
            [
                'role' => 'user',
                'content' => "أعِد صياغة النصّ التالي ({$langLabel}) {$instructions[$mode]}:\n\n"
                    .'«'.$text.'»'
                    ."\n\nأعِد JSON فقط: {\"rewrite\": \"..\"}",
            ],
        ]);

        return trim((string) ($data['rewrite'] ?? ''));
    }

    /**
     * اقتراحات وسوم ذكية واعية بالعربية: كيانات/أشخاص/أماكن/منظمات/مواضيع.
     *
     * @param  array<string,mixed>  $context
     * @return array{people:array<int,string>,locations:array<int,string>,organizations:array<int,string>,topics:array<int,string>}
     */
    public function suggestTags(array $context): array
    {
        $data = $this->chatJson([
            $this->systemMessage(
                'أنت محلّل محتوى عربي. تستخرج الوسوم الأكثر صلة من النصّ: أسماء الأشخاص '
                .'والأماكن والمنظمات والمواضيع. وسوم قصيرة دقيقة، دون تكرار أو عموميات.'
            ),
            [
                'role' => 'user',
                'content' => "استخرج وسوماً للمحتوى التالي.\n\n"
                    .$this->contextBlock($context)
                    ."\n\nأعِد JSON فقط بالشكل:\n"
                    .'{"people": [], "locations": [], "organizations": [], "topics": []}',
            ],
        ]);

        return [
            'people' => $this->stringList($data['people'] ?? [], 10),
            'locations' => $this->stringList($data['locations'] ?? [], 10),
            'organizations' => $this->stringList($data['organizations'] ?? [], 10),
            'topics' => $this->stringList($data['topics'] ?? [], 10),
        ];
    }

    /**
     * تحليل سيو استشاري: طول العنوان، جودة الوصف، كلمات ناقصة، اقتراحات.
     *
     * @param  array<string,mixed>  $payload
     * @return array{score:int,title_feedback:string,description_feedback:string,missing_keywords:array<int,string>,suggestions:array<int,string>}
     */
    public function analyzeSeo(array $payload): array
    {
        $data = $this->chatJson([
            $this->systemMessage(
                'أنت خبير سيو تحريري. تُقيّم جودة العنوان والوصف والوسوم لمحرّكات البحث، '
                .'وتعطي ملاحظات عملية موجزة بالعربية. النتيجة استشارية فقط.'
            ),
            [
                'role' => 'user',
                'content' => "حلّل عناصر السيو التالية.\n\n"
                    .'العنوان: '.(string) ($payload['title'] ?? '')."\n"
                    .'الوصف/المقتطف: '.(string) ($payload['excerpt'] ?? '')."\n"
                    .'الـ slug: '.(string) ($payload['slug'] ?? '')."\n"
                    .'الوسوم: '.implode('، ', $this->stringList($payload['tags'] ?? [], 30))."\n\n"
                    ."أعِد JSON فقط بالشكل:\n"
                    .'{"score": 0-100, "title_feedback": "..", "description_feedback": "..", '
                    .'"missing_keywords": [], "suggestions": ["..اقتراحات تحسين.."]}',
            ],
        ]);

        return [
            'score' => max(0, min(100, (int) ($data['score'] ?? 0))),
            'title_feedback' => trim((string) ($data['title_feedback'] ?? '')),
            'description_feedback' => trim((string) ($data['description_feedback'] ?? '')),
            'missing_keywords' => $this->stringList($data['missing_keywords'] ?? [], 15),
            'suggestions' => $this->stringList($data['suggestions'] ?? [], 10),
        ];
    }

    /**
     * تحليل جودة المحتوى تحريرياً: التكرار، ضعف البنية/الصياغة، سهولة القراءة،
     * وملاحظات لغوية. اقتراحات استشارية فقط — لا يعدّل النصّ ولا يحفظه.
     *
     * @param  array<string,mixed>  $context  title, subtitle, body, locale
     * @return array{score:int,readability:string,issues:array<int,string>,suggestions:array<int,string>}
     */
    public function analyzeContent(array $context): array
    {
        $data = $this->chatJson([
            $this->systemMessage(
                'أنت محرّر مدقّق في غرفة أخبار عربية. تُقيّم جودة المحتوى التحريري: '
                .'التكرار، ضعف البنية والصياغة، سهولة القراءة، والملاحظات اللغوية. '
                .'تعطي ملاحظات عملية موجزة بالعربية دون إعادة كتابة النصّ. النتيجة استشارية فقط.'
            ),
            [
                'role' => 'user',
                'content' => "حلّل جودة المحتوى التالي تحريرياً.\n\n"
                    .$this->contextBlock($context)
                    ."\n\nأعِد JSON فقط بالشكل:\n"
                    .'{"score": 0-100, "readability": "..وصف موجز لسهولة القراءة..", '
                    .'"issues": ["..مشكلات محدّدة: تكرار/بنية/صياغة/لغة.."], '
                    .'"suggestions": ["..اقتراحات تحسين عملية.."]}',
            ],
        ]);

        return [
            'score' => max(0, min(100, (int) ($data['score'] ?? 0))),
            'readability' => trim((string) ($data['readability'] ?? '')),
            'issues' => $this->stringList($data['issues'] ?? [], 12),
            'suggestions' => $this->stringList($data['suggestions'] ?? [], 12),
        ];
    }

    // ─── Internals ───────────────────────────────────────────────────────────

    /** @return array{role:string,content:string} */
    private function systemMessage(string $content): array
    {
        // أسلوب الكتابة المُهيّأ في اللوحة (إن وُجد) يوجّه نبرة كل الاقتراحات.
        $style = trim($this->settings->openai_writing_style);
        $styleHint = $style !== '' ? ' أسلوب التحرير المطلوب: '.$style.'.' : '';

        return [
            'role' => 'system',
            'content' => $content.$styleHint
                .' أجِب دائماً بـ JSON صالح فقط دون أي نصّ إضافي أو علامات Markdown.',
        ];
    }

    /** @param array<string,mixed> $context */
    private function contextBlock(array $context): string
    {
        $parts = [];
        if (! empty($context['type'])) {
            $parts[] = 'نوع المحتوى: '.(string) $context['type'];
        }
        if (! empty($context['categories'])) {
            $parts[] = 'التصنيفات: '.implode('، ', $this->stringList($context['categories'], 10));
        }
        if (! empty($context['title'])) {
            $parts[] = 'العنوان الحالي: '.(string) $context['title'];
        }
        if (! empty($context['subtitle'])) {
            $parts[] = 'العنوان الفرعي: '.(string) $context['subtitle'];
        }
        if (! empty($context['excerpt'])) {
            $parts[] = 'المقتطف: '.(string) $context['excerpt'];
        }
        if (! empty($context['body'])) {
            // قصّ المتن لتفادي تجاوز الحدّ — أوّل ~4000 حرف تكفي للسياق.
            $body = mb_substr((string) $context['body'], 0, 4000);
            $parts[] = "المتن:\n".$body;
        }

        return implode("\n", $parts);
    }

    /**
     * نداء يُعيد JSON مُحلَّلاً. متسامح مع أسوار الشيفرة (```json) إن ظهرت.
     *
     * @param  array<int,array{role:string,content:string}>  $messages
     * @return array<string,mixed>
     */
    private function chatJson(array $messages): array
    {
        $raw = $this->provider->chat($messages, ['json' => true]);

        $clean = trim($raw);
        // إزالة أسوار الشيفرة إن وُجدت.
        if (str_starts_with($clean, '```')) {
            $clean = (string) preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $clean);
        }
        // اقتطاع إلى أوّل/آخر قوس لتجاوز أي ثرثرة محيطة.
        $start = strpos($clean, '{');
        $end = strrpos($clean, '}');
        if ($start !== false && $end !== false && $end >= $start) {
            $clean = substr($clean, $start, $end - $start + 1);
        }

        $decoded = json_decode($clean, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('ai_unparseable_response');
        }

        return $decoded;
    }

    /**
     * يطبّع قيمة إلى قائمة نصوص نظيفة مقصوصة بحدّ أقصى.
     *
     * @return array<int,string>
     */
    private function stringList(mixed $value, int $max): array
    {
        if (! is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $item) {
            if (! is_string($item) && ! is_numeric($item)) {
                continue;
            }
            $s = self::tidy((string) $item);
            if ($s !== '') {
                $out[] = $s;
            }
            if (count($out) >= $max) {
                break;
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * ينظّف نصّ اقتراح من تشويش النموذج: ترقيم القوائم، الرموز النقطية،
     * علامات الاقتباس المحيطة، وتأكيد Markdown — فيظهر العنوان/الوسم صِرفاً.
     */
    private static function tidy(string $s): string
    {
        $s = trim($s);
        // إزالة بادئة الترقيم/التعداد: "1. " أو "1) " أو "- " أو "• " أو "* ".
        $s = (string) preg_replace('/^\s*(?:\d+\s*[.)\-]|[-•*])\s+/u', '', $s);
        // إزالة تأكيد Markdown (**نص** / *نص*).
        $s = (string) preg_replace('/\*{1,2}(.+?)\*{1,2}/u', '$1', $s);
        // إزالة علامات الاقتباس المحيطة (إنجليزية/عربية/ذكية).
        $s = trim($s, " \t\n\r\0\x0B\"'«»“”‘’");

        return trim($s);
    }
}
