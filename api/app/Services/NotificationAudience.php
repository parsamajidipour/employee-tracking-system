<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Collection;

final class NotificationAudience
{
    /**
     * @return Collection<int, User>
     */
    public function managers(?int $exceptUserId = null): Collection
    {
        return User::query()
            ->whereIn('role', [UserRole::Admin->value, UserRole::Supervisor->value])
            ->when($exceptUserId !== null, fn ($query) => $query->whereKeyNot($exceptUserId))
            ->get();
    }

    /**
     * @return Collection<int, User>
     */
    public function activeEmployees(): Collection
    {
        return User::query()->employees()->active()->get();
    }
}
