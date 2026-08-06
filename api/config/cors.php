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

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
