<?php

declare(strict_types=1);

namespace App\Actions\Public\Content;

use App\Enums\EpaperAccessLevel;
use App\Http\Resources\Public\Content\PublicEpaperListItemResource;
use App\Models\Epaper;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * قائمة الأعداد الرقميّة (PDF) المنشورة وذات الوصول العام، للغة محدّدة،
 * مرتّبة من الأحدث. تُحمّل أصل الـ PDF لإخراج رابطه. الأعداد المقيّدة الوصول
 * لا تُدرَج في القائمة العامّة.
 */
class ListPublicEpapersAction
{
    private const MAX = 60;

    public function handle(string $locale): JsonResponse
    {
        if (! in_array($locale, Epaper::LOCALES, true)) {
            return ApiResponse::error(__('article.invalid_locale'), [], 422);
        }

        $items = Epaper::query()
            ->published()
            ->forLocale($locale)
            ->where('access_level', EpaperAccessLevel::Public->value)
            ->with('mediaAsset')
            ->orderByDesc('publication_date')
            ->orderByDesc('issue_number')
            ->orderByDesc('id')
            ->limit(self::MAX)
            ->get();

        return ApiResponse::success(data: PublicEpaperListItemResource::collection($items)->resolve());
    }
}
