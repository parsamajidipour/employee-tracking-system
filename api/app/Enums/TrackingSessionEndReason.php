<?php

namespace App\Enums;

enum TrackingSessionEndReason: string
{
    case WindowClosed = 'window_closed';
    case ManualPause = 'manual_pause';
    case PermissionRevoked = 'permission_revoked';
    case Stale = 'stale';
}
