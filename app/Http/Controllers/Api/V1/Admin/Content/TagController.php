<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin\Content;

use App\Actions\Admin\Content\DeleteTagAction;
use App\Actions\Admin\Content\ListManagedTagsAction;
use App\Actions\Admin\Content\ListTagsAction;
use App\Actions\Admin\Content\UpdateTagAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\UpdateTagRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Tags\Tag;

class TagController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return (new ListTagsAction)->handle(
            (string) $request->query('locale', 'ar'),
            (string) $request->query('q', ''),
            (int) $request->query('limit', 20),
        );
    }

    /** قائمة إدارة الوسوم — مرقّمة مع عدّاد الاستخدام (tags.view). */
    public function managed(Request $request): JsonResponse
    {
        return (new ListManagedTagsAction)->handle(
            (string) $request->query('locale', 'ar'),
            (string) $request->query('q', ''),
        );
    }

    /** إعادة تسمية وسم (tags.edit). */
    public function update(UpdateTagRequest $request, Tag $tag): JsonResponse
    {
        return (new UpdateTagAction)->handle($tag, $request->validated());
    }

    /** حذف وسم وفصله عن كل المحتوى (tags.delete). */
    public function destroy(Tag $tag): JsonResponse
    {
        return (new DeleteTagAction)->handle($tag);
    }
}
