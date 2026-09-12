<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class ReverseGeocoder
{
    private const RATE_LIMIT_KEY = 'nominatim:reverse-geocode';

    public function reverse(float $lat, float $lng): ?string
    {
        $roundedLat = round($lat, 5);
        $roundedLng = round($lng, 5);
        $cacheKey = sprintf('reverse-geocode:%.5f:%.5f', $roundedLat, $roundedLng);
        $cached = Cache::get($cacheKey);

        if (is_array($cached) && array_key_exists('location', $cached)) {
            return is_string($cached['location']) ? $cached['location'] : null;
        }

        if (! Cache::add(self::RATE_LIMIT_KEY, true, 1)) {
            throw new TooManyRequestsHttpException(
                1,
                'Location lookup is temporarily busy. Try again in a moment.',
            );
        }

        $location = $this->requestLocation($roundedLat, $roundedLng);

        Cache::put($cacheKey, ['location' => $location], now()->addDays(30));

        return $location;
    }

    private function requestLocation(float $lat, float $lng): ?string
    {
        $response = Http::acceptJson()
            ->withUserAgent(sprintf('SmartInspection/1.0 (+%s)', config('app.url')))
            ->withHeaders([
                'Accept-Language' => 'en',
                'Referer' => config('app.url'),
            ])
            ->connectTimeout(3)
            ->timeout(6)
            ->get(config('services.nominatim.reverse_url'), [
                'format' => 'jsonv2',
                'lat' => $lat,
                'lon' => $lng,
                'zoom' => 18,
                'addressdetails' => 1,
            ])
            ->throw();

        $displayName = $response->json('display_name');

        if (! is_string($displayName) || trim($displayName) === '') {
            return null;
        }

        return Str::limit(trim($displayName), 255, '');
    }
}
