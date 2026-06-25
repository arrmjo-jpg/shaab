<?php

declare(strict_types=1);

namespace App\Support\Epaper\Ocr;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * مزوّد OCR عبر Google Document AI (REST v1). يُرسل الـ PDF خاماً (لا تحويل صور
 * محلّياً) إلى مُعالِج مُهيّأ، ويعيد نصّ كل صفحة من مراسي النصّ (text anchors).
 * المصادقة عبر JWT لحساب الخدمة (RS256) ⇄ access token مُخزَّن مؤقتاً. الاعتماد
 * من config/env فقط — لا أسرار في قاعدة البيانات. أيّ خطأ صلب يُرمى ليسجّله الخطّ
 * failed (وللمركّب أن يلتقطه ويعود للمضمَّن).
 */
final class GoogleDocumentAiProvider implements EpaperOcrProvider
{
    public function extract(string $pdfPath): OcrExtraction
    {
        $creds = $this->credentials();
        $project = (string) config('epaper.ocr.google.project_id', '');
        $location = (string) config('epaper.ocr.google.location', 'us');
        $processor = (string) config('epaper.ocr.google.processor_id', '');

        if ($project === '' || $processor === '' || ($creds['client_email'] ?? '') === '' || ($creds['private_key'] ?? '') === '') {
            throw new RuntimeException('epaper.ocr: Google Document AI is not fully configured.');
        }

        $content = @file_get_contents($pdfPath);
        if ($content === false) {
            throw new RuntimeException('epaper.ocr: unable to read PDF for Document AI.');
        }

        $endpoint = "https://{$location}-documentai.googleapis.com/v1/projects/{$project}/locations/{$location}/processors/{$processor}:process";

        $response = Http::withToken($this->accessToken($creds))
            ->timeout((int) config('epaper.ocr.google.timeout', 120))
            ->post($endpoint, [
                'rawDocument' => [
                    'content' => base64_encode($content),
                    'mimeType' => 'application/pdf',
                ],
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('epaper.ocr: Document AI process failed (HTTP '.$response->status().').');
        }

        return $this->parse((array) $response->json('document', []));
    }

    /**
     * يحوّل وثيقة Document AI إلى نصّ لكل صفحة عبر مراسي النصّ (إزاحات بايتية في
     * document.text بترميز UTF-8).
     *
     * @param  array<string,mixed>  $document
     */
    private function parse(array $document): OcrExtraction
    {
        $fullText = (string) ($document['text'] ?? '');
        $pages = [];

        foreach ((array) ($document['pages'] ?? []) as $i => $page) {
            $num = (int) ($page['pageNumber'] ?? ($i + 1));
            $segments = (array) ($page['layout']['textAnchor']['textSegments'] ?? []);
            $text = '';
            foreach ($segments as $seg) {
                $start = (int) ($seg['startIndex'] ?? 0);
                $end = (int) ($seg['endIndex'] ?? 0);
                if ($end > $start) {
                    $text .= substr($fullText, $start, $end - $start);
                }
            }
            $pages[$num] = trim($text);
        }

        // وثيقة بنصّ كامل دون مراسي صفحات صريحة ⇒ صفحة واحدة بالنصّ الكامل.
        if ($pages === [] && $fullText !== '') {
            $pages[1] = trim($fullText);
        }

        return new OcrExtraction($pages, 'google_document_ai');
    }

    /** access token مُخزَّن مؤقتاً (≈50 دقيقة) من تدفّق JWT لحساب الخدمة. */
    private function accessToken(array $creds): string
    {
        $cacheKey = 'epaper.ocr.google.token:'.md5((string) ($creds['client_email'] ?? ''));

        return Cache::remember($cacheKey, 3000, function () use ($creds): string {
            $tokenUri = (string) ($creds['token_uri'] ?? 'https://oauth2.googleapis.com/token');
            $now = time();

            $header = $this->base64url((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64url((string) json_encode([
                'iss' => $creds['client_email'] ?? '',
                'scope' => 'https://www.googleapis.com/auth/cloud-platform',
                'aud' => $tokenUri,
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            $input = $header.'.'.$claims;
            $signature = '';
            if (! openssl_sign($input, $signature, (string) ($creds['private_key'] ?? ''), OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('epaper.ocr: failed to sign Google service-account assertion.');
            }

            $jwt = $input.'.'.$this->base64url($signature);

            $response = Http::asForm()->timeout(20)->post($tokenUri, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            $token = (string) $response->json('access_token', '');
            if (! $response->successful() || $token === '') {
                throw new RuntimeException('epaper.ocr: Google token exchange failed.');
            }

            return $token;
        });
    }

    /** @return array<string,mixed> */
    private function credentials(): array
    {
        $raw = (string) config('epaper.ocr.google.credentials', '');
        if ($raw === '') {
            return [];
        }
        if (is_file($raw)) {
            $raw = (string) @file_get_contents($raw);
        }
        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function base64url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
