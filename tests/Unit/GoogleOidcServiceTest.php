<?php

namespace Tests\Unit;

use App\Services\GoogleOidcService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class GoogleOidcServiceTest extends TestCase
{
    public function test_it_cryptographically_verifies_a_valid_google_id_token(): void
    {
        [$token, $jwk] = $this->signedToken('expected-nonce');

        Cache::forget('google.oidc.jwks');
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response([
                'keys' => [$jwk],
            ]),
        ]);

        $claims = app(GoogleOidcService::class)->verifyIdToken(
            $token,
            'test-client.apps.googleusercontent.com',
            'expected-nonce'
        );

        $this->assertSame('verified-user@gmail.com', $claims['email']);
        $this->assertTrue($claims['email_verified']);
    }

    public function test_it_rejects_a_google_id_token_with_the_wrong_nonce(): void
    {
        [$token, $jwk] = $this->signedToken('original-nonce');

        Cache::forget('google.oidc.jwks');
        Http::fake([
            'https://www.googleapis.com/oauth2/v3/certs' => Http::response([
                'keys' => [$jwk],
            ]),
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Google ID token nonce.');

        app(GoogleOidcService::class)->verifyIdToken(
            $token,
            'test-client.apps.googleusercontent.com',
            'different-nonce'
        );
    }

    private function signedToken(string $nonce): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);
        $details = openssl_pkey_get_details($privateKey);
        $keyId = 'test-google-signing-key';
        $header = $this->base64UrlEncode(json_encode([
            'alg' => 'RS256',
            'kid' => $keyId,
            'typ' => 'JWT',
        ]));
        $payload = $this->base64UrlEncode(json_encode([
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client.apps.googleusercontent.com',
            'exp' => time() + 3600,
            'iat' => time(),
            'nonce' => $nonce,
            'sub' => 'google-account-id',
            'email' => 'verified-user@gmail.com',
            'email_verified' => true,
        ]));
        $signingInput = $header.'.'.$payload;

        openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        return [
            $signingInput.'.'.$this->base64UrlEncode($signature),
            [
                'kid' => $keyId,
                'kty' => 'RSA',
                'alg' => 'RS256',
                'use' => 'sig',
                'n' => $this->base64UrlEncode($details['rsa']['n']),
                'e' => $this->base64UrlEncode($details['rsa']['e']),
            ],
        ];
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
