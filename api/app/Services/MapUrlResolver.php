<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class MapUrlResolver
{
    private const ALLOWED_HOSTS = [
        'google.com',
        'maps.google.com',
        'www.google.com',
        'maps.app.goo.gl',
        'goo.gl',
        'openstreetmap.org',
        'www.openstreetmap.org',
        'maps.apple.com',
    ];

    /**
     * @return array{lat: float, lng: float}|null
     */
    public function resolve(string $url): ?array
    {
        $current = trim($url);

        for ($redirect = 0; $redirect <= 4; $redirect++) {
            if (! $this->isAllowed($current)) {
                return null;
            }

            $coordinates = $this->extract($current);
            if ($coordinates !== null) {
                return $coordinates;
            }

            if ($redirect === 4) {
                break;
            }

            $response = Http::withoutRedirecting()->timeout(6)->get($current);
            $location = $response->header('Location');

            if (! is_string($location) || $location === '') {
                break;
            }

            $current = $this->absoluteUrl($current, $location);
        }

        return null;
    }

    private function isAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, ['http', 'https'], true)
            && in_array($host, self::ALLOWED_HOSTS, true);
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function extract(string $url): ?array
    {
        $decoded = rawurldecode($url);
        $patterns = [
            '/!3d(-?\d{1,2}(?:\.\d+)?)!4d([+-]?\d{1,3}(?:\.\d+)?)/i',
            '/[@!](-?\d{1,2}(?:\.\d+)?)[,!]([+-]?\d{1,3}(?:\.\d+)?)/',
            '/[#?&](?:map=\d+(?:\.\d+)?\/|ll=|query=|destination=|center=|q=)(-?\d{1,2}(?:\.\d+)?)[,\/]\s*([+-]?\d{1,3}(?:\.\d+)?)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $decoded, $match) === 1) {
                return $this->coordinates((float) $match[1], (float) $match[2]);
            }
        }

        return null;
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function coordinates(float $lat, float $lng): ?array
    {
        if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
            return null;
        }

        return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
    }

    private function absoluteUrl(string $base, string $location): string
    {
        if (parse_url($location, PHP_URL_SCHEME) !== null) {
            return $location;
        }

        $scheme = (string) parse_url($base, PHP_URL_SCHEME);
        if (str_starts_with($location, '//')) {
            return $scheme.':'.$location;
        }

        $host = (string) parse_url($base, PHP_URL_HOST);

        return $scheme.'://'.$host.'/'.ltrim($location, '/');
    }
}
