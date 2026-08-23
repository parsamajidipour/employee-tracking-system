<?php

namespace App\Enums;

enum CasePriority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}
