<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Auth\AuthUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RequireStaff
{
    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->attributes->get('authUser');

        if (! $authUser instanceof AuthUser || ! $authUser->isStaff) {
            throw new AccessDeniedHttpException('Staff access required');
        }

        return $next($request);
    }
}
