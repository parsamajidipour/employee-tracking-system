<?php

namespace App\Enums;

enum ShiftExceptionType: string
{
    case Leave = 'leave';
    case Holiday = 'holiday';
    case Overtime = 'overtime';
    case EarlyEnd = 'early_end';

    public function isDeny(): bool
    {
        return $this === self::Leave || $this === self::Holiday;
    }

    public function definesWindow(): bool
    {
        return $this === self::Overtime || $this === self::EarlyEnd;
    }
}
