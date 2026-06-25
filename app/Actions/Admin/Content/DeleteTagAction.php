<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Tags\Tag;

/**
 * حذف وسم — يفصله عن كل المحتوى (taggables) ثم يحذفه، ضمن معاملة. الفصل الصريح
 * يضمن النظافة بصرف النظر عن تفعيل قيود المفتاح الأجنبيّ في المحرّك (إنتاج/اختبار).
 */
class DeleteTagAction
{
    public function handle(Tag $tag): JsonResponse
    {
        DB::transaction(function () use ($tag): void {
            DB::table('taggables')->where('tag_id', $tag->id)->delete();
            $tag->delete();
        });

        return ApiResponse::success(message: __('tag.deleted'));
    }
}
