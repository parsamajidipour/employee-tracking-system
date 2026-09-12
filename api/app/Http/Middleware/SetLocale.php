<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $language = strtolower(substr((string) $request->header('Accept-Language', 'en'), 0, 2));
        app()->setLocale(in_array($language, ['en', 'ar'], true) ? $language : 'en');

        return $next($request);
    }
}
