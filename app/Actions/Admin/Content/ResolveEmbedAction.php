<?php

declare(strict_types=1);

namespace App\Actions\Admin\Content;

use App\Support\Media\EmbedResolver;
use App\Support\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;

/**
 * يطبّع رابط تضمين إلى بيانات allow-list للمحرّر (لا تخزين — كتلة محتوى).
 */
class ResolveEmbedAction
{
    public function handle(string $url): JsonResponse
    {
        $resolved = EmbedResolver::resolve($url);

        if ($resolved === null) {
            return ApiResponse::error(__('media.unsupported_embed'), [], 422);
        }

        return ApiResponse::success(__('media.embed_resolved'), $resolved);
    }
}
