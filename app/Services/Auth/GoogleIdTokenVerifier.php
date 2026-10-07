<?php

declare(strict_types=1);

namespace App\Services\Auth;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Throwable;
use UnexpectedValueException;

class GoogleIdTokenVerifier
{
    private const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const CACHE_KEY = 'google_oauth_certs';

    /** @var list<string> */
    private const ISSUERS = ['accounts.google.com', 'https://accounts.google.com'];

    /**
     * @return array{sub: string, email: string}
     */
    public function verify(string $idToken): array
    {
        $clientId = trim((string) config('services.google.client_id'));

        if ($clientId === '') {
            throw new UnauthorizedHttpException('', 'Google sign-in is not configured.');
        }

        $payload = $this->decode($idToken);

        $issuer = (string) ($payload->iss ?? '');
        if (! in_array($issuer, self::ISSUERS, true)) {
            throw new UnauthorizedHttpException('', 'Google sign-in failed.');
        }

        $audience = $payload->aud ?? '';
        $audiences = is_array($audience) ? $audience : [$audience];
        if (! in_array($clientId, $audiences, true)) {
            throw new UnauthorizedHttpException('', 'Google sign-in failed.');
        }

        $emailVerified = $payload->email_verified ?? false;
        if ($emailVerified !== true && $emailVerified !== 'true') {
            throw new UnauthorizedHttpException('', 'Google account email is not verified.');
        }

        $email = strtolower(trim((string) ($payload->email ?? '')));
        $sub = trim((string) ($payload->sub ?? ''));

        if ($email === '' || $sub === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new UnauthorizedHttpException('', 'Google sign-in failed.');
        }

        return [
            'sub' => $sub,
            'email' => $email,
        ];
    }

    private function decode(string $idToken): object
    {
        try {
            return $this->decodeWithKeys($idToken, $this->keySet());
        } catch (UnexpectedValueException $exception) {
            if (! str_contains(strtolower($exception->getMessage()), 'kid')) {
                throw new UnauthorizedHttpException('', 'Google sign-in failed.');
            }

            Cache::forget(self::CACHE_KEY);

            try {
                return $this->decodeWithKeys($idToken, $this->keySet());
            } catch (Throwable) {
                throw new UnauthorizedHttpException('', 'Google sign-in failed.');
            }
        } catch (UnauthorizedHttpException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new UnauthorizedHttpException('', 'Google sign-in failed.');
        }
    }

    /**
     * @param  array<string, mixed>  $keys
     */
    private function decodeWithKeys(string $idToken, array $keys): object
    {
        $previousLeeway = JWT::$leeway;
        JWT::$leeway = 60;

        try {
            return JWT::decode($idToken, $keys);
        } finally {
            JWT::$leeway = $previousLeeway;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function keySet(): array
    {
        $jwks = Cache::remember(self::CACHE_KEY, 3600, function (): array {
            $response = Http::timeout(5)->acceptJson()->get(self::CERTS_URL);

            $body = $response->json();
            if (! $response->successful() || ! is_array($body) || ! isset($body['keys']) || ! is_array($body['keys'])) {
                throw new UnexpectedValueException('Unable to fetch Google certificates.');
            }

            return $body;
        });

        if (! is_array($jwks)) {
            Cache::forget(self::CACHE_KEY);
            throw new UnauthorizedHttpException('', 'Google sign-in failed.');
        }

        return JWK::parseKeySet($jwks);
    }
}
