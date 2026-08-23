<?php

namespace App\Enums;

enum InterruptionReason: string
{
    case GpsDisabled = 'gps_disabled';
    case NetworkDisabled = 'network_disabled';
    case FlightMode = 'flight_mode';
    case PermissionRevoked = 'permission_revoked';
    case ServiceInterrupted = 'service_interrupted';
}
