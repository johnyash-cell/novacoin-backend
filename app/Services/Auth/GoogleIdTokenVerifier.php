<?php

namespace App\Services\Auth;

use App\Contracts\Auth\GoogleIdTokenVerifierContract;
use App\Exceptions\InvalidGoogleIdTokenException;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class GoogleIdTokenVerifier implements GoogleIdTokenVerifierContract
{
    private const GOOGLE_CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

    private const ACCEPTED_ISSUERS = [
        'https://accounts.google.com',
        'accounts.google.com',
    ];

    public function verify(string $idToken): VerifiedGoogleUserProfile
    {
        $clientId = config('services.google.client_id');

        if (! filled($clientId)) {
            throw InvalidGoogleIdTokenException::becauseTokenCouldNotBeVerified();
        }

        try {
            $keys = $this->googlePublicKeys();
            $payload = JWT::decode($idToken, JWK::parseKeySet($keys));
        } catch (Throwable) {
            throw InvalidGoogleIdTokenException::becauseTokenCouldNotBeVerified();
        }

        $audience = $payload->aud ?? null;
        $issuer = $payload->iss ?? null;
        $googleId = $payload->sub ?? null;
        $email = $payload->email ?? null;

        if (! is_string($audience) || $audience !== $clientId) {
            throw InvalidGoogleIdTokenException::becauseTokenCouldNotBeVerified();
        }

        if (! is_string($issuer) || ! in_array($issuer, self::ACCEPTED_ISSUERS, true)) {
            throw InvalidGoogleIdTokenException::becauseTokenCouldNotBeVerified();
        }

        if (! is_string($googleId) || $googleId === '') {
            throw InvalidGoogleIdTokenException::becauseTokenCouldNotBeVerified();
        }

        if (! is_string($email) || $email === '') {
            throw InvalidGoogleIdTokenException::becauseTokenCouldNotBeVerified();
        }

        $givenName = is_string($payload->given_name ?? null) ? $payload->given_name : '';
        $familyName = is_string($payload->family_name ?? null) ? $payload->family_name : '';
        $fullName = is_string($payload->name ?? null) ? $payload->name : '';

        if ($givenName === '' && $fullName !== '') {
            $nameParts = preg_split('/\s+/', trim($fullName), 2) ?: [];
            $givenName = $nameParts[0] ?? 'Google';
            $familyName = $familyName !== '' ? $familyName : ($nameParts[1] ?? 'User');
        }

        if ($givenName === '') {
            $givenName = 'Google';
        }

        if ($familyName === '') {
            $familyName = 'User';
        }

        return new VerifiedGoogleUserProfile(
            googleId: $googleId,
            email: $email,
            firstName: $givenName,
            lastName: $familyName,
            isEmailVerified: (bool) ($payload->email_verified ?? false),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function googlePublicKeys(): array
    {
        /** @var array<string, mixed> $keys */
        $keys = Cache::remember('google_oauth_jwk_keys', now()->addHour(), function (): array {
            $response = Http::timeout(5)->get(self::GOOGLE_CERTS_URL);

            if (! $response->successful()) {
                throw InvalidGoogleIdTokenException::becauseTokenCouldNotBeVerified();
            }

            /** @var array<string, mixed> $body */
            $body = $response->json();

            return $body;
        });

        return $keys;
    }
}
