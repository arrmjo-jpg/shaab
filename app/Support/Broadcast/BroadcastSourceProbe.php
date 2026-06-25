<?php

declare(strict_types=1);

namespace App\Support\Broadcast;

use App\Enums\BroadcastSourceType;
use App\Models\Broadcast;
use App\Support\Security\SafeUrl;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * فاحص صحّة مصدر البثّ — **آمن ضدّ SSRF بشكل إنتاجي** (يُعامَل المصدر كعدائي):
 *   1) إعادة تحقّق allow-list + https + SafeUrl (سلاسل) — fail-safe.
 *   2) حارس إعادة-ربط DNS (مُحلّ العناوين عامة) — قابل للتعطيل في الاختبارات.
 *   3) مهلة صارمة (connect + total)، **بلا متابعة إعادة توجيه** (لا قفزة لعنوان خاص).
 *   4) قراءة محدودة (مانيفست صغير / Range للصوت) — لا تنزيل بثّ كامل.
 *
 * دلالات حسب النوع (ليست «200 = سليم» الساذجة):
 *   - HLS/IPTV : جلب المانيفست والتحقّق أنه يبدأ بـ #EXTM3U.
 *   - Icecast/Shoutcast : Range 0-0 + نوع محتوى صوتي (وصول — وكيل عن حيّة الصوت).
 *   - يوتيوب/مزوّد خارجي : غير قابل للفحص خادمياً ⇒ notProbeable (لا فشل).
 */
final class BroadcastSourceProbe
{
    public function probe(Broadcast $broadcast): BroadcastProbeResult
    {
        if (! $broadcast->source_type->isProbeable()) {
            return BroadcastProbeResult::notProbeable();
        }

        $url = (string) $broadcast->source_url;

        // (1) حارس السلاسل: allow-list + https + SafeUrl (يمنع المضيفات الخاصّة/المُبهَمة).
        if (! BroadcastSourceValidator::isAllowed($broadcast->source_type->value, $url)) {
            return BroadcastProbeResult::failed('untrusted_source');
        }

        // (2) حارس إعادة-ربط DNS (وقت-الاتصال) — مُعطَّل في الاختبارات لعزل الشبكة.
        if (config('broadcast.health.verify_resolved_ip')) {
            $host = (string) parse_url($url, PHP_URL_HOST);
            if (! SafeUrl::hostResolvesToPublicIp($host)) {
                return BroadcastProbeResult::failed('private_or_unresolvable_host');
            }
        }

        return match ($broadcast->source_type) {
            BroadcastSourceType::Hls, BroadcastSourceType::Iptv => $this->probeManifest($url),
            BroadcastSourceType::Icecast, BroadcastSourceType::Shoutcast => $this->probeAudio($url),
            default => BroadcastProbeResult::notProbeable(),
        };
    }

    private function probeManifest(string $url): BroadcastProbeResult
    {
        $start = microtime(true);

        try {
            $response = $this->client()
                ->withHeaders(['Accept' => 'application/vnd.apple.mpegurl,application/x-mpegURL,*/*'])
                ->get($url);
        } catch (Throwable) {
            return BroadcastProbeResult::failed('connection_error');
        }

        $latency = $this->latencyMs($start);

        if ($response->redirect()) {
            return BroadcastProbeResult::failed('unexpected_redirect', $latency);
        }
        if (! $response->ok()) {
            return BroadcastProbeResult::failed('http_'.$response->status(), $latency);
        }

        // قراءة محدودة: المانيفست نصّي صغير — نفحص البادئة فقط.
        $head = ltrim(substr($response->body(), 0, 64));
        if (! str_starts_with($head, '#EXTM3U')) {
            return BroadcastProbeResult::failed('not_a_manifest', $latency);
        }

        return BroadcastProbeResult::healthy($latency);
    }

    private function probeAudio(string $url): BroadcastProbeResult
    {
        $start = microtime(true);

        try {
            // Range 0-0 يحدّ القراءة لبايت واحد (لا نستهلك بثّاً لا نهائياً).
            $response = $this->client()
                ->withHeaders(['Range' => 'bytes=0-0', 'Icy-MetaData' => '1'])
                ->get($url);
        } catch (Throwable) {
            return BroadcastProbeResult::failed('connection_error');
        }

        $latency = $this->latencyMs($start);

        if ($response->redirect()) {
            return BroadcastProbeResult::failed('unexpected_redirect', $latency);
        }
        if (! in_array($response->status(), [200, 206], true)) {
            return BroadcastProbeResult::failed('http_'.$response->status(), $latency);
        }

        $contentType = strtolower((string) $response->header('Content-Type'));
        $isAudio = str_starts_with($contentType, 'audio/')
            || str_contains($contentType, 'ogg')
            || str_contains($contentType, 'mpegurl'); // بعض المحطّات تقدّم playlist
        if (! $isAudio) {
            return BroadcastProbeResult::failed('not_audio_stream', $latency);
        }

        return BroadcastProbeResult::healthy($latency);
    }

    private function client(): PendingRequest
    {
        return Http::connectTimeout((int) config('broadcast.health.connect_timeout', 3))
            ->timeout((int) config('broadcast.health.timeout', 5))
            ->withoutRedirecting();
    }

    private function latencyMs(float $start): int
    {
        return (int) round((microtime(true) - $start) * 1000);
    }
}
