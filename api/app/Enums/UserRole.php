<?php

namespace App\Enums;

/**
 * Access is decided by capability (App\Enums\Capability), not by comparing
 * roles against each other. There is deliberately no rank/hierarchy here —
 * a prior version used one ("hr and up"), which meant hr silently inherited
 * supervisor's location-viewing access. The person who sets working hours
 * (manage-schedules: admin, hr) and the person who watches where people are
 * (view-locations: admin, supervisor) are separate concerns by design; only
 * admin is deliberately in both, see DECISIONS.md.
 */
enum UserRole: string
{
    case Employee = 'employee';
    case Supervisor = 'supervisor';
    case Hr = 'hr';
    case Admin = 'admin';

    /**
     * @return list<Capability>
     */
    public function capabilities(): array
    {
        return match ($this) {
            self::Admin => [Capability::ManageSchedules, Capability::ViewLocations],
            self::Hr => [Capability::ManageSchedules],
            self::Supervisor => [Capability::ViewLocations],
            self::Employee => [],
        };
    }

    public function can(Capability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }
}
