<?php

declare(strict_types=1);

namespace App\Support\Responses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Exceptions\UnauthorizedException as SpatieUnauthorizedException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * يحوّل أي استثناء إلى استجابة API موحّدة عبر ApiResponse.
 *
 * - جميع الرسائل من ملفات الترجمة (lang/ar/api.php) — لا نصوص مضمّنة.
 * - لا يُكشف getMessage() الخام لأي استثناء (إطار أو vendor).
 * - HttpException العام يُترجَم برمز الحالة فقط — رسالة مُسيطَر عليها.
 *
 * ترتيب match مقصود: الأنواع الأكثر تحديداً قبل HttpException العام.
 */
final class ApiExceptionRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            // ─── 422 — فشل التحقق ────────────────────────────────────
            $e instanceof ValidationException => ApiResponse::error(
                __('api.validation_failed'),
                $e->errors(),
                422
            ),

            // ─── 401 — غير مصادَق ────────────────────────────────────
            $e instanceof AuthenticationException => ApiResponse::error(
                __('api.unauthenticated'),
                [],
                401
            ),

            // ─── 429 — تجاوز حد الطلبات ──────────────────────────────
            $e instanceof ThrottleRequestsException => ApiResponse::error(
                __('api.throttled'),
                [],
                429
            ),

            // ─── 403 — ممنوع (Spatie + Policies + Symfony) ──────────
            $e instanceof SpatieUnauthorizedException,
            $e instanceof AccessDeniedHttpException,
            $e instanceof AuthorizationException => ApiResponse::error(
                __('api.forbidden'),
                [],
                403
            ),

            // ─── 404 — غير موجود ─────────────────────────────────────
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException => ApiResponse::error(
                __('api.not_found'),
                [],
                404
            ),

            // ─── 405 — طريقة غير مسموحة ──────────────────────────────
            $e instanceof MethodNotAllowedHttpException => ApiResponse::error(
                __('api.method_not_allowed'),
                [],
                405
            ),

            // ─── HttpException العام — رسالة مُترجَمة بالرمز فقط ──────
            // لا نكشف $e->getMessage() — قد يحمل نصاً من الإطار أو vendor
            $e instanceof HttpException => ApiResponse::error(
                self::messageForStatus($e->getStatusCode()),
                [],
                $e->getStatusCode()
            ),

            // ─── 500 — خطأ غير متوقع ─────────────────────────────────
            default => ApiResponse::error(
                __('api.unexpected_error'),
                [],
                500
            ),
        };
    }

    /**
     * رسالة مُترجَمة آمنة بناءً على رمز الحالة فقط.
     */
    private static function messageForStatus(int $status): string
    {
        return match ($status) {
            401 => __('api.unauthenticated'),
            403 => __('api.forbidden'),
            404 => __('api.not_found'),
            405 => __('api.method_not_allowed'),
            429 => __('api.throttled'),
            default => __('api.bad_request'),
        };
    }
}
