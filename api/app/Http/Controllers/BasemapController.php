<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BasemapController extends Controller
{
    public function oman(): BinaryFileResponse|JsonResponse
    {
        $path = storage_path('app/basemap/oman.pmtiles');

        if (! is_readable($path)) {
            return response()->json([
                'message' => 'Basemap extract is missing. Build it once per clone, see README step 3.',
            ], 503);
        }

        return response()->file($path, [
            'Content-Type' => 'application/octet-stream',
            'Cache-Control' => 'public, max-age=604800, immutable',
        ]);
    }
}
