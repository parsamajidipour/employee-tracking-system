<?php

namespace App\Enums;

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
            self::Admin => [Capability::ManageSchedules, Capability::ViewLocations, Capability::ManageReleases],
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
