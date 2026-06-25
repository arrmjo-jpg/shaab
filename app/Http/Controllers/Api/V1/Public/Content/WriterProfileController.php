<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Public\Content;

use App\Actions\Public\Content\ShowPublicWriterAction;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

/**
 * بروفيل كاتب عامّ — `GET /{locale}/writers/{id}`. البوّابة (is_writer نشِط) في الـ Action.
 * locale معامل مسار فقط (بيانات الكاتب مستقلّة عن اللغة).
 */
class WriterProfileController extends Controller
{
    public function show(string $locale, int $id): JsonResponse
    {
        return (new ShowPublicWriterAction)->handle($id);
    }
}
