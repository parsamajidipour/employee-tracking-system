<?php

namespace App\Events;

use App\Http\Resources\CaseResource;
use App\Models\InspectionCase;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Http\Request;

final class CaseChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public readonly string $action,
        public readonly int $caseId,
        /** @var array<string, mixed>|null */
        public readonly ?array $snapshot = null,
    ) {}

    public static function for(string $action, InspectionCase $case): self
    {
        $fresh = InspectionCase::query()
            ->withLatLng()
            ->with('assignee')
            ->find($case->id);

        return new self(
            $action,
            $case->id,
            $fresh === null ? null : CaseResource::make($fresh)->resolve(Request::create('/')),
        );
    }

    public static function deleted(int $caseId): self
    {
        return new self('deleted', $caseId);
    }

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('cases')];
    }

    public function broadcastAs(): string
    {
        return 'case.changed';
    }

    /**
     * @return array{action: string, case_id: int, case: array<string, mixed>|null}
     */
    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'case_id' => $this->caseId,
            'case' => $this->snapshot,
        ];
    }
}
