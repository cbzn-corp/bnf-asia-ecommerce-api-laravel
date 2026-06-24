<?php

use App\Http\Middleware\AuthenticateJwt;
use App\Http\Middleware\OptionalJwtAuth;
use App\Http\Middleware\RequireCustomer;
use App\Http\Middleware\RequirePermissions;
use App\Http\Middleware\RequireStaff;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \Illuminate\Http\Middleware\HandleCors::class,
        ]);

        $middleware->alias([
            'bnf.authenticate' => AuthenticateJwt::class,
            'bnf.authenticate.optional' => OptionalJwtAuth::class,
            'require.staff' => RequireStaff::class,
            'require.customer' => RequireCustomer::class,
            'require.permissions' => RequirePermissions::class,
            'staff' => RequireStaff::class,
            'permissions' => RequirePermissions::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            return response()->json([
                'statusCode' => Response::HTTP_BAD_REQUEST,
                'message' => collect($e->errors())->flatten()->values()->all(),
                'error' => 'Bad Request',
            ], Response::HTTP_BAD_REQUEST);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $statusCode = $e->getStatusCode();
            $message = $e->getMessage();

            if ($message === '') {
                $message = Response::$statusTexts[$statusCode] ?? 'Error';
            }

            return response()->json([
                'statusCode' => $statusCode,
                'message' => $message,
                'error' => nestHttpErrorLabel($statusCode),
            ], $statusCode);
        });
    })->create();

function nestHttpErrorLabel(int $statusCode): string
{
    return match ($statusCode) {
        Response::HTTP_BAD_REQUEST => 'Bad Request',
        Response::HTTP_UNAUTHORIZED => 'Unauthorized',
        Response::HTTP_FORBIDDEN => 'Forbidden',
        Response::HTTP_NOT_FOUND => 'Not Found',
        Response::HTTP_METHOD_NOT_ALLOWED => 'Method Not Allowed',
        Response::HTTP_CONFLICT => 'Conflict',
        Response::HTTP_UNPROCESSABLE_ENTITY => 'Unprocessable Entity',
        Response::HTTP_TOO_MANY_REQUESTS => 'Too Many Requests',
        Response::HTTP_INTERNAL_SERVER_ERROR => 'Internal Server Error',
        default => Response::$statusTexts[$statusCode] ?? 'Error',
    };
}
