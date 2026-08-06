<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Restricted to the Nuxt panel's origin(s), with credentials enabled —
    | the SPA session cookie and CSRF cookie only work with an explicit
    | origin list, never with "*".
    |
    */

    // broadcasting/auth: Laravel Echo's private-channel authorization
    // request (see panel/'s live map) — a cross-origin XHR/fetch from the
    // browser, same as api/* and sanctum/csrf-cookie, and needs the same
    // credentialed-CORS treatment or the browser blocks the response
    // before Echo ever sees whether the channel closure allowed it.
    'paths' => ['api/*', 'sanctum/csrf-cookie', 'broadcasting/auth'],

    'allowed_methods' => ['*'],

    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:3000')),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    // pmtiles' browser client (see panel/app/pages/map.vue) reads Range
    // requests against api/basemap/oman.pmtiles via fetch(), and fetch()
    // hides every response header from JS by default on a cross-origin
    // request unless it's explicitly exposed here — Content-Length and
    // Accept-Ranges are how the client sizes its first request; Content-Range
    // is how it confirms which byte range actually came back.
    'exposed_headers' => ['Content-Range', 'Content-Length', 'Accept-Ranges'],

    'max_age' => 0,

    'supports_credentials' => true,

];
