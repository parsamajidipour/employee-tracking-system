<?php

namespace App\Http\Middleware;

use App\Enums\Capability;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

class EnsureCapability
{
    public static function passes(User $user, Capability $capability): bool
    {
        if ($user->currentAccessToken() instanceof PersonalAccessToken) {
            return false;
        }

        return $user->role->can($capability);
    }

    public function handle(Request $request, Closure $next, string $capability): Response
    {
        if (! self::passes($request->user(), Capability::from($capability))) {
            abort(403, 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
