<?php

declare(strict_types=1);

namespace App\Support\Content;

use App\Models\Article;
use App\Settings\GeneralSettings;

/**
 * بوّابة التعليقات العامة — العقد الإلزاميّ: العالميّ ∧ المقال.
 *
 *   enabledFor = GeneralSettings.comments_enabled AND article.comments_enabled
 *
 * علم الواجهة `client.flags.comments` شأن build للواجهة العامة ولا يُفرَض خادميّاً
 * (يُطبَّق في شريحة الـFrontend). مصدر الحقيقة الخادميّ هو هذان العَلَمان فقط.
 */
final class CommentGuard
{
    public static function enabledFor(Article $article): bool
    {
        return app(GeneralSettings::class)->comments_enabled
            && (bool) $article->comments_enabled;
    }
}
