<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->isActive()) {
            return ApiResponse::error(
                __('auth.account_inactive'),
                [],
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
