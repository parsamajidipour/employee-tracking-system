<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

final class CaseAccessChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    /**
     * @param  list<int>  $employeeIds
     */
    public function __construct(
        public readonly int $caseId,
        public readonly array $employeeIds,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(
            fn (int $employeeId) => new PrivateChannel('App.Models.User.'.$employeeId),
            $this->employeeIds,
        );
    }

    public function broadcastAs(): string
    {
        return 'case.access-changed';
    }

    /**
     * @return array{type: string, case_id: int}
     */
    public function broadcastWith(): array
    {
        return [
            'type' => 'case.access-changed',
            'case_id' => $this->caseId,
        ];
    }
}
