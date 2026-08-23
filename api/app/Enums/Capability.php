<?php

namespace App\Enums;

enum Capability: string
{
    case ManageSchedules = 'manage-schedules';
    case ViewLocations = 'view-locations';
    case ManageReleases = 'manage-releases';
    case ManageCases = 'manage-cases';
    case ViewCases = 'view-cases';
}
