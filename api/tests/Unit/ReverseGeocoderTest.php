<?php

namespace Tests\Unit;

use App\Services\ReverseGeocoder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

class ReverseGeocoderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.url', 'https://manaba.example');
        config()->set('services.nominatim.reverse_url', 'https://nominatim.example/reverse');
        Cache::flush();
        Http::preventStrayRequests();
    }

    public function test_it_returns_and_caches_a_location(): void
    {
        Http::fake([
            'nominatim.example/*' => Http::response([
                'display_name' => 'Al Khuwair, Muscat, Oman',
            ]),
        ]);

        $geocoder = app(ReverseGeocoder::class);

        $this->assertSame('Al Khuwair, Muscat, Oman', $geocoder->reverse(23.598101, 58.378581));
        $this->assertSame('Al Khuwair, Muscat, Oman', $geocoder->reverse(23.598104, 58.378584));

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => str_starts_with($request->url(), 'https://nominatim.example/reverse?')
            && $request->hasHeader('User-Agent', 'SmartInspection/1.0 (+https://manaba.example)')
            && $request->hasHeader('Referer', 'https://manaba.example')
            && $request->hasHeader('Accept-Language', 'en')
        );
    }

    public function test_it_limits_uncached_provider_requests_to_one_per_second(): void
    {
        Http::fake([
            'nominatim.example/*' => Http::response([
                'display_name' => 'Muscat, Oman',
            ]),
        ]);

        $geocoder = app(ReverseGeocoder::class);
        $geocoder->reverse(23.59, 58.37);

        $this->expectException(TooManyRequestsHttpException::class);

        $geocoder->reverse(23.60, 58.38);
    }
}
