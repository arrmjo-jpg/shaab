<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Ai;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Ai\AnalyzeContentRequest;
use App\Http\Requests\Admin\Ai\AnalyzeSeoRequest;
use App\Http\Requests\Admin\Ai\GenerateExcerptRequest;
use App\Http\Requests\Admin\Ai\RewriteTextRequest;
use App\Http\Requests\Admin\Ai\SuggestHeadlinesRequest;
use App\Http\Requests\Admin\Ai\SuggestTagsRequest;
use App\Support\Ai\AiCostGuard;
use App\Support\Ai\AiEditorialService;
use App\Support\Ai\AiUsageLog;
use App\Support\Ai\EditorialHeuristics;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * المساعد التحريري (Copilot) — مساعدة لا توليد تلقائي.
 *
 * المزوّد ومفاتيحه يُقرآن من إعدادات اللوحة (ThirdPartySettings). الميزات
 * الحتمية المُهيكلة (الملخّص + الوسوم + السيو) لا تتعطّل أبداً: تسقط إلى بدائل
 * حتمية (EditorialHeuristics) عند إيقاف/تعذّر الذكاء الاصطناعي أو تجاوز الحدّ.
 * أمّا الميزات التي تتطلّب ذكاءً فعلياً (العناوين + إعادة الصياغة + تحليل
 * المحتوى) فتُرجِع 503 لطيفة (تعذّر) أو 429 (تجاوز حدّ التكلفة/الاستخدام).
 * التفويض ai.use، وتحديد المعدّل throttle:ai (على مستوى المسار).
 */
class AiCopilotController extends Controller
{
    public function __construct(
        private readonly AiEditorialService $ai,
        private readonly EditorialHeuristics $heuristics,
    ) {}

    public function headlines(SuggestHeadlinesRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->aiOnly('headlines', $v, fn (): array => $this->ai->suggestHeadlines($v));
    }

    public function rewrite(RewriteTextRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->aiOnly('rewrite', $v, fn (): array => [
            'rewrite' => $this->ai->rewrite($v['text'], $v['mode'], $v['locale'] ?? 'ar'),
        ]);
    }

    /** تحليل جودة المحتوى — يتطلّب ذكاءً اصطناعياً (لا بديل حتمي معقول). */
    public function analyze(AnalyzeContentRequest $request): JsonResponse
    {
        $v = $request->validated();

        return $this->aiOnly('analyze', $v, fn (): array => $this->ai->analyzeContent($v));
    }

    /** الوسوم: ذكاء اصطناعي إن توفّر ولم يُتجاوز الحدّ، وإلا توليد حتمي واعٍ بالعربية. */
    public function tags(SuggestTagsRequest $request): JsonResponse
    {
        $v = $request->validated();

        if ($this->ai->available() && AiCostGuard::exceeded() === null) {
            try {
                $tags = $this->ai->suggestTags($v);
                AiUsageLog::record('tags', 'ai', $this->ai->provider()->name(), self::size($v), self::size($tags));

                return ApiResponse::success(data: ['source' => 'ai'] + $tags);
            } catch (Throwable $e) {
                report($e);
            }
        }

        AiUsageLog::record('tags', 'auto', 'none');

        return ApiResponse::success(data: ['source' => 'auto'] + $this->heuristics->tags($v));
    }

    /** الملخّص: ذكاء اصطناعي إن توفّر ولم يُتجاوز الحدّ، وإلا بديل حتمي من العنوان + المتن. */
    public function excerpt(GenerateExcerptRequest $request): JsonResponse
    {
        $v = $request->validated();

        if ($this->ai->available() && AiCostGuard::exceeded() === null) {
            try {
                $excerpt = $this->ai->generateExcerpt($v);
                AiUsageLog::record('excerpt', 'ai', $this->ai->provider()->name(), self::size($v), mb_strlen($excerpt));

                return ApiResponse::success(data: ['excerpt' => $excerpt, 'source' => 'ai']);
            } catch (Throwable $e) {
                report($e);
            }
        }

        AiUsageLog::record('excerpt', 'auto', 'none');

        return ApiResponse::success(data: [
            'excerpt' => $this->heuristics->excerpt($v['title'] ?? '', $v['body'] ?? ''),
            'source' => 'auto',
        ]);
    }

    /** تحليل السيو: ذكاء اصطناعي إن توفّر ولم يُتجاوز الحدّ، وإلا تقييم قواعدي من العنوان + المتن. */
    public function seo(AnalyzeSeoRequest $request): JsonResponse
    {
        $v = $request->validated();

        if ($this->ai->available() && AiCostGuard::exceeded() === null) {
            try {
                $analysis = $this->ai->analyzeSeo($v);
                AiUsageLog::record('seo', 'ai', $this->ai->provider()->name(), self::size($v), self::size($analysis));

                return ApiResponse::success(data: ['source' => 'ai'] + $analysis);
            } catch (Throwable $e) {
                report($e);
            }
        }

        AiUsageLog::record('seo', 'auto', 'none');

        return ApiResponse::success(data: ['source' => 'auto'] + $this->heuristics->seo($v));
    }

    /**
     * الميزات التي تتطلّب ذكاءً اصطناعياً فعلياً (لا بديل حتمي معقول):
     * - 503 لطيفة عند الإيقاف/التعذّر بدل تجميد الواجهة.
     * - 429 لطيفة عند تجاوز حدّ التكلفة/الاستخدام (حماية إنتاجية).
     *
     * @param  array<string,mixed>  $input
     * @param  callable():array<string,mixed>  $operation
     */
    private function aiOnly(string $action, array $input, callable $operation): JsonResponse
    {
        if (! $this->ai->available()) {
            return ApiResponse::error(__('ai.not_configured'), [], 503);
        }

        if (AiCostGuard::exceeded() !== null) {
            return ApiResponse::error(__('ai.quota_exceeded'), [], 429);
        }

        try {
            $data = $operation();
            AiUsageLog::record($action, 'ai', $this->ai->provider()->name(), self::size($input), self::size($data));

            return ApiResponse::success(data: $data);
        } catch (Throwable $e) {
            report($e);

            return ApiResponse::error(__('ai.unavailable'), [], 503);
        }
    }

    /** تقدير حجم حمولة بالأحرف (لتقدير التوكِنات/التكلفة) — يونيكود-آمن. */
    private static function size(array $payload): int
    {
        return mb_strlen((string) json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
}
