<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Models\Category;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

class ListTrashedCategoriesAction
{
    public function handle(): JsonResponse
    {
        // قائمة مسطّحة للمحذوفات (التصنيف لا يُحذَف إن كان له أبناء، فالمحذوف ورقة).
        // اسم الأب يُضاف للسياق دون كشف بنية داخلية.
        $trashed = Category::onlyTrashed()
            ->orderByDesc('deleted_at')
            ->get()
            ->map(fn (Category $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'locale' => $c->locale,
                'scope' => $c->scope->value,
                'parent_id' => $c->parent_id,
                'parent_name' => $c->parent_id !== null
                    ? Category::withTrashed()->find($c->parent_id)?->name
                    : null,
                'deleted_at' => $c->deleted_at?->toISOString(),
            ])
            ->values();

        return ApiResponse::success(data: $trashed);
    }
}
