<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Auth\AuthService;
use Closure;
use Illuminate\Http\Request;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth as JWTAuthFacade;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

class AuthenticateJwt
{
    public function __construct(
        private readonly AuthService $authService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            throw new UnauthorizedHttpException('', 'Unauthorized');
        }

        try {
            $payload = JWTAuthFacade::setToken($token)->getPayload();
            $userId = $payload->get('id') ?? $payload->get('sub');

            if ($userId === null || $userId === '') {
                throw new UnauthorizedHttpException('', 'Unauthorized');
            }

            $authUser = $this->authService->validateUser((string) $userId);

            if ($authUser === null) {
                throw new UnauthorizedHttpException('', 'Unauthorized');
            }

            $request->attributes->set('authUser', $authUser);
        } catch (JWTException) {
            throw new UnauthorizedHttpException('', 'Unauthorized');
        }

        return $next($request);
    }
}
