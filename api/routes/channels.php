<?php

use App\Enums\Capability;
use App\Http\Middleware\EnsureCapability;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Live-map position deltas (App\Events\EmployeePositionUpdated). Gated on
// the view-locations capability via the same check EnsureCapability uses for
// GET /api/v1/positions — including rejecting a device-bound mobile token
// outright, regardless of its owner's role. A channel any authenticated user
// could subscribe to would bypass every gate that middleware already builds.
Broadcast::channel('positions', function ($user) {
    return EnsureCapability::passes($user, Capability::ViewLocations);
});
