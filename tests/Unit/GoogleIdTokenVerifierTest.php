<?php

namespace Tests\Unit;

use App\Services\Auth\GoogleIdTokenVerifier;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Tests\TestCase;

class GoogleIdTokenVerifierTest extends TestCase
{
    private string $privateKey;

    private string $modulus;

    private string $exponent;

    private ?string $opensslConfig = null;

    protected function setUp(): void
    {
        parent::setUp();

        $key = $this->generateKey();
        $this->privateKey = $this->exportKey($key);

        $details = openssl_pkey_get_details($key);
        $this->assertIsArray($details);
        $this->modulus = $this->base64Url($details['rsa']['n']);
        $this->exponent = $this->base64Url($details['rsa']['e']);

        config(['services.google.client_id' => 'test-client.apps.googleusercontent.com']);
        Cache::flush();

        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response([
                'keys' => [[
                    'kty' => 'RSA',
                    'alg' => 'RS256',
                    'use' => 'sig',
                    'kid' => 'test-key',
                    'n' => $this->modulus,
                    'e' => $this->exponent,
                ]],
            ]),
        ]);
    }

    public function test_accepts_a_verified_google_id_token(): void
    {
        $token = $this->token();

        $identity = app(GoogleIdTokenVerifier::class)->verify($token);

        $this->assertSame('google-subject', $identity['sub']);
        $this->assertSame('shopper@gmail.com', $identity['email']);
    }

    public function test_rejects_a_token_for_a_different_client(): void
    {
        $token = $this->token(['aud' => 'other-client']);

        $this->expectException(UnauthorizedHttpException::class);

        app(GoogleIdTokenVerifier::class)->verify($token);
    }

    public function test_rejects_an_unverified_email(): void
    {
        $token = $this->token(['email_verified' => false]);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Google account email is not verified.');

        app(GoogleIdTokenVerifier::class)->verify($token);
    }

    public function test_rejects_when_google_sign_in_is_not_configured(): void
    {
        config(['services.google.client_id' => '']);

        $this->expectException(UnauthorizedHttpException::class);
        $this->expectExceptionMessage('Google sign-in is not configured.');

        app(GoogleIdTokenVerifier::class)->verify($this->token());
    }

    private function generateKey(): \OpenSSLAsymmetricKey
    {
        $options = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];

        $key = openssl_pkey_new($options);
        if ($key instanceof \OpenSSLAsymmetricKey) {
            return $key;
        }

        foreach ([
            'C:/xampp/php/extras/ssl/openssl.cnf',
            'C:/xampp/apache/conf/openssl.cnf',
        ] as $config) {
            if (! is_file($config)) {
                continue;
            }

            $key = openssl_pkey_new([...$options, 'config' => $config]);
            if ($key instanceof \OpenSSLAsymmetricKey) {
                $this->opensslConfig = $config;

                return $key;
            }
        }

        $this->fail('OpenSSL could not generate a test key.');
    }

    private function exportKey(\OpenSSLAsymmetricKey $key): string
    {
        $options = $this->opensslConfig !== null ? ['config' => $this->opensslConfig] : null;
        $exported = '';
        $this->assertTrue(openssl_pkey_export($key, $exported, null, $options));

        return $exported;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function token(array $overrides = []): string
    {
        $payload = array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client.apps.googleusercontent.com',
            'sub' => 'google-subject',
            'email' => 'shopper@gmail.com',
            'email_verified' => true,
            'iat' => time(),
            'exp' => time() + 3600,
        ], $overrides);

        return JWT::encode($payload, $this->privateKey, 'RS256', 'test-key');
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
