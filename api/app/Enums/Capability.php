<?php

namespace App\Enums;

/**
 * What a role can *do*, not how roles compare to each other — see
 * App\Enums\UserRole::can(). Deliberately not derived from rank/hierarchy:
 * that's what let hr inherit supervisor's access before this existed.
 */
enum Capability: string
{
    case ManageSchedules = 'manage-schedules';
    case ViewLocations = 'view-locations';
}
