<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class CopernicusTokenService
{
    protected const CACHE_NAMESPACE = 'copernicus.access_token';

    public static function hasClientCredentials(): bool
    {
        $clientId = (string) config('services.copernicus.client_id');
        $clientSecret = (string) config('services.copernicus.client_secret');

        return trim($clientId) !== '' && trim($clientSecret) !== '';
    }

    public static function getAccessToken(): ?string
    {
        if (!self::hasClientCredentials()) {
            return null;
        }

        $clientId = (string) config('services.copernicus.client_id');
        $clientSecret = (string) config('services.copernicus.client_secret');
        $cacheKey = self::cacheKey($clientId);
        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            $token = isset($cached['token']) && is_string($cached['token']) ? trim($cached['token']) : null;
            $expiresAt = isset($cached['expires_at']) && is_int($cached['expires_at']) ? $cached['expires_at'] : null;

            if ($token !== null && $token !== '' && $expiresAt !== null && $expiresAt > (time() + 60)) {
                return $token;
            }
        }

        return self::requestNewToken($clientId, $clientSecret, $cacheKey);
    }

    protected static function cacheKey(string $clientId): string
    {
        return sprintf('%s.%s', self::CACHE_NAMESPACE, sha1($clientId));
    }

    protected static function requestNewToken(string $clientId, string $clientSecret, string $cacheKey): ?string
    {
        $endpoint = (string) config('services.copernicus.token_endpoint', 'https://identity.dataspace.copernicus.eu/auth/realms/CDSE/protocol/openid-connect/token');

        try {
            $response = Http::asForm()
                ->acceptJson()
                ->post($endpoint, [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                ]);
        } catch (Throwable $exception) {
            Log::error('Failed to request Copernicus access token.', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }

        if (!$response->successful()) {
            Log::warning('Copernicus access token request failed.', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        }

        $payload = $response->json();
        if (!is_array($payload)) {
            $decoded = json_decode($response->body(), true);
            $payload = is_array($decoded) ? $decoded : [];
        }

        $token = isset($payload['access_token']) && is_string($payload['access_token'])
            ? trim($payload['access_token'])
            : (isset($payload['token']) && is_string($payload['token']) ? trim($payload['token']) : '');

        if ($token === '') {
            Log::warning('Copernicus token response did not include an access token.');

            return null;
        }

        $expiresIn = isset($payload['expires_in']) ? (int) $payload['expires_in'] : 0;
        $configuredTtl = (int) config('services.copernicus.token_cache_seconds', 3300);
        $configuredTtl = $configuredTtl > 0 ? $configuredTtl : 3300;

        if ($expiresIn > 0) {
            $ttl = min($configuredTtl, max(60, $expiresIn - 60));
        } else {
            $ttl = max(300, $configuredTtl);
        }

        $expiresAt = time() + $ttl;

        Cache::put($cacheKey, [
            'token' => $token,
            'expires_at' => $expiresAt,
        ], $ttl);

        return $token;
    }
}
