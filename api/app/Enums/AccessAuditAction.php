<?php

namespace App\Enums;

enum AccessAuditAction: string
{
    case ViewTrail = 'view_trail';
    case ExportTrail = 'export_trail';
    case ViewLivePosition = 'view_live_position';
}
