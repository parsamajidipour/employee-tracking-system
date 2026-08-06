<?php

namespace App\Http\Controllers;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the self-hosted PMTiles basemap archive (see DECISIONS.md's "Live
 * map tiles" entry). Unauthenticated on purpose — this is generic OSM
 * basemap geometry, not employee location data, and the pmtiles JS protocol
 * handler has no mechanism to attach a session cookie to its range requests
 * anyway.
 *
 * `response()->file()` returns a BinaryFileResponse, and Laravel calls
 * ->prepare($request) on every response before sending it (Router::
 * prepareResponse) — that's what turns a `Range` request header into a 206
 * partial-content reply with a matching Content-Range. Nothing extra to
 * implement for the byte-range reads pmtiles relies on.
 */
class BasemapController extends Controller
{
    public function oman(): BinaryFileResponse
    {
        return response()->file(storage_path('app/basemap/oman.pmtiles'));
    }
}
