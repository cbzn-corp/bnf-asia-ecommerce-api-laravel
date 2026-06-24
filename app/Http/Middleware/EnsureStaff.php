<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class EnsureStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user()?->loadMissing('role');

        if (! $user?->role?->isStaff) {
            throw new AccessDeniedHttpException('Staff access required');
        }

        return $next($request);
    }
}
