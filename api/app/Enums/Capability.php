<?php

namespace App\Enums;

enum Capability: string
{
    case ManageSchedules = 'manage-schedules';
    case ViewLocations = 'view-locations';
}
