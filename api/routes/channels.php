<?php

use App\Enums\Capability;
use App\Http\Middleware\EnsureCapability;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('positions', function ($user) {
    return EnsureCapability::passes($user, Capability::ViewLocations);
});
