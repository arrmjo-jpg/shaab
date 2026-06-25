<?php

declare(strict_types=1);

namespace App\Support\Vertix;

use App\Support\Content\HtmlToTipTap;
use App\Support\Content\TipTapRenderer;
use App\Support\Content\TipTapSanitizer;

/**
 * يحوّل متن خبر Vertix (HTML قديم) إلى مستند TipTap + HTML نظيف **بأعلى وفاء، بلا فقدان**.
 *
 * يعيد استخدام المحوّل المحايد HtmlToTipTap (يحفظ الصور والروابط والعناوين والقوائم والجداول
 * والاقتباسات والتضمينات)، ثمّ TipTapSanitizer (allow-list) و TipTapRenderer. **بلا strip_tags.**
 *
 * الصورة البارزة لا تُحقَن في المتن (تُسحَب غلافاً إلى MediaAsset عبر VertixImageImporter). وإن
 * ظهرت الصورة البارزة نفسها كأوّل صورة في صدر المتن (تكرار) تُزال **تلك فقط**؛ بقيّة صور المتن
 * تبقى كما جاءت من المصدر (بروابطها الأصليّة، بلا نقل إلى MediaAsset في هذه المرحلة).
 */
final class VertixContentTransformer
{
    /**
     * @param  string|null  $featuredUrl  رابط الصورة البارزة — لإزالة تكرارها من صدر المتن فقط.
     * @return array{doc: array<string,mixed>, html: string}
     */
    public static function transform(?string $body, ?string $featuredUrl = null): array
    {
        $doc = HtmlToTipTap::transform((string) ($body ?? ''))['doc'];
        $doc = self::dropLeadingFeatured($doc, $featuredUrl);

        // مستند TipTap يحتاج كتلةً واحدةً على الأقلّ (متن فارغ ⇒ فقرة فارغة).
        if (($doc['content'] ?? []) === []) {
            $doc['content'] = [['type' => 'paragraph']];
        }

        $clean = TipTapSanitizer::clean($doc);

        return [
            'doc' => $clean,
            'html' => TipTapRenderer::toHtml($clean),
        ];
    }

    /**
     * يزيل **فقط** الصورة الأولى في صدر المتن إن طابق مصدرها رابط الصورة البارزة (صارت غلافاً).
     * بقيّة صور المتن لا تُمَسّ. لا رابط بارز ⇒ بلا أيّ إزالة.
     *
     * @param  array<string,mixed>  $doc
     * @return array<string,mixed>
     */
    private static function dropLeadingFeatured(array $doc, ?string $featuredUrl): array
    {
        if ($featuredUrl === null || $featuredUrl === '') {
            return $doc;
        }

        $content = $doc['content'] ?? [];
        $first = $content[0] ?? null;
        if (is_array($first)
            && ($first['type'] ?? null) === 'image'
            && (string) ($first['attrs']['src'] ?? '') === $featuredUrl
        ) {
            array_shift($content);
            $doc['content'] = array_values($content);
        }

        return $doc;
    }
}
