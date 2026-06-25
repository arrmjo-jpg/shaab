<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Http\Resources\Admin\Content\TagResource;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Tags\Tag;

/**
 * إعادة تسمية وسم — يضبط ترجمة الاسم لكل لغة مزوَّدة، ويُعيد توليد الـslug عبر نفس
 * slugger الافتراضيّ لـ Spatie (Str::slug). لو أنتج slugger نصاً فارغاً (مثل العربية)
 * يُبقى الـslug القائم بلا تغيير — تطابقاً لسلوك الإنشاء ودون كسر روابط قائمة.
 */
class UpdateTagAction
{
    /** @param  array{name: array<string,string|null>}  $validated */
    public function handle(Tag $tag, array $validated): JsonResponse
    {
        foreach ($validated['name'] as $locale => $value) {
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }

            $tag->setTranslation('name', $locale, $value);

            $slug = Str::slug($value);
            if ($slug !== '') {
                $tag->setTranslation('slug', $locale, $slug);
            }
        }

        $tag->save();

        $tag->usage_count = (int) DB::table('taggables')->where('tag_id', $tag->id)->count();

        return ApiResponse::success(
            message: __('tag.updated'),
            data: new TagResource($tag),
        );
    }
}
