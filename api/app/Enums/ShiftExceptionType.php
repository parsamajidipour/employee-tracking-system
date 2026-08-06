<?php

namespace App\Enums;

enum ShiftExceptionType: string
{
    case Leave = 'leave';
    case Holiday = 'holiday';
    case Overtime = 'overtime';
    case EarlyEnd = 'early_end';

    /**
     * Types that deny the day outright — no window, regardless of what any
     * template would otherwise say.
     */
    public function isDeny(): bool
    {
        return $this === self::Leave || $this === self::Holiday;
    }

    /**
     * Types that define the day's window from this row's own start_at/end_at.
     */
    public function definesWindow(): bool
    {
        return $this === self::Overtime || $this === self::EarlyEnd;
    }
}
