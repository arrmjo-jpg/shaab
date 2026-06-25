<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureWriter
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->is_writer !== true) {
            return ApiResponse::error(
                __('api.forbidden'),
                [],
                Response::HTTP_FORBIDDEN
            );
        }

        return $next($request);
    }
}
