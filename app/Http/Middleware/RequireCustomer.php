<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Config\Permissions;
use App\Support\Auth\AuthUser;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class RequireCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        $authUser = $request->attributes->get('authUser');

        if (! $authUser instanceof AuthUser || $authUser->roleKey !== Permissions::CUSTOMER_ROLE_KEY) {
            throw new AccessDeniedHttpException('Customer access required');
        }

        return $next($request);
    }
}
