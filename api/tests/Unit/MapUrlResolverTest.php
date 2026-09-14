<?php

namespace Tests\Unit;

use App\Services\MapUrlResolver;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MapUrlResolverTest extends TestCase
{
    public function test_extracts_google_openstreetmap_and_apple_coordinates(): void
    {
        $resolver = app(MapUrlResolver::class);

        $this->assertSame(
            ['lat' => 23.6144, 'lng' => 58.5922],
            $resolver->resolve('https://www.google.com/maps/place/Muscat/@23.6144,58.5922,15z'),
        );
        $this->assertSame(
            ['lat' => 23.6144, 'lng' => 58.5922],
            $resolver->resolve('https://www.google.com/maps/place/Muscat/data=!3d23.6144!4d58.5922'),
        );
        $this->assertSame(
            ['lat' => 23.6144, 'lng' => 58.5922],
            $resolver->resolve('https://www.openstreetmap.org/#map=15/23.6144/58.5922'),
        );
        $this->assertSame(
            ['lat' => 23.6144, 'lng' => 58.5922],
            $resolver->resolve('https://maps.apple.com/?ll=23.6144,58.5922'),
        );
    }

    public function test_follows_only_allowlisted_short_map_redirects(): void
    {
        Http::fake([
            'https://maps.app.goo.gl/abc' => Http::response('', 302, [
                'Location' => 'https://www.google.com/maps/@23.6144,58.5922,15z',
            ]),
        ]);

        $this->assertSame(
            ['lat' => 23.6144, 'lng' => 58.5922],
            app(MapUrlResolver::class)->resolve('https://maps.app.goo.gl/abc'),
        );
        $this->assertNull(app(MapUrlResolver::class)->resolve('https://example.com/?q=23.6144,58.5922'));
    }
}
