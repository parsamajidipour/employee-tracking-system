<?php

namespace App\Http\Middleware;

use App\Enums\Capability;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate a route behind a App\Enums\Capability — `capability:manage-schedules`,
 * `capability:view-locations`. Registered as the `capability` alias in
 * bootstrap/app.php. Applies to every panel/admin route; never to /me/* or
 * /track, which stay open to any authenticated user for their own data.
 *
 * Deliberately checks a capability, not a role rank — see App\Enums\UserRole's
 * docblock for why (hr and supervisor are separate concerns, not tiers of the
 * same one; only admin holds both).
 *
 * A device-bound mobile token belongs to an employee (SPEC section 5) and
 * every route this middleware guards is a panel/admin surface — so token
 * auth is rejected here outright, before the capability check even runs,
 * regardless of what the token owner's role column says now or comes to
 * say after a later promotion. Session (Sanctum SPA cookie) auth carries a
 * TransientToken, not a PersonalAccessToken, which is what makes this
 * check possible: see Laravel\Sanctum\Guard::__invoke().
 *
 * passes() is the same check factored out for routes/channels.php's
 * `positions` channel authorization, which needs a boolean, not a request
 * pipeline to abort out of — one implementation of "does this user, with
 * this token, have this capability," used by both.
 */
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
