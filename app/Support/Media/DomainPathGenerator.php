<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Models\Article;
use App\Models\User;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * مسارات وسائط منظَّمة حسب النطاق + سنة/شهر + UUID (قرار مقفول).
 *
 *   uploads/articles/YYYY/MM/{uuid}/
 *   uploads/live/YYYY/MM/{uuid}/         (جاهز لوحدة التغطية الحيّة لاحقاً)
 *   uploads/authors/YYYY/MM/{uuid}/
 *   uploads/settings/branding/{uuid}/    (مسطّح — للمستقبل، نظام الإعدادات
 *                                         الحالي MediaAsset لا يُمَسّ هنا)
 *
 * القرص نفسه قابل للتبديل عبر MEDIA_DISK (R2 مستقبلاً) دون لمس هذا المولّد.
 */
class DomainPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->base($media);
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->base($media).'conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->base($media).'responsive/';
    }

    private function base(Media $media): string
    {
        $uuid = $media->uuid ?: (string) $media->getKey();
        $domain = $this->domain($media);

        if ($domain === 'settings/branding') {
            return "settings/branding/{$uuid}/";
        }

        $date = ($media->created_at ?? now());

        return sprintf('%s/%s/%s/%s/', $domain, $date->format('Y'), $date->format('m'), $uuid);
    }

    private function domain(Media $media): string
    {
        if ($media->collection_name === 'branding') {
            return 'settings/branding';
        }

        return match ($media->model_type) {
            Article::class => 'articles',
            User::class => 'authors',
            'App\\Models\\ArticleUpdate' => 'live',
            default => 'misc',
        };
    }
}
