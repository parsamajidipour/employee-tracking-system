<?php

namespace App\Notifications;

use App\Models\InspectionCase;
use App\Notifications\Concerns\BroadcastsToInbox;
use Illuminate\Notifications\Notification;

class CaseAssignedNotification extends Notification
{
    use BroadcastsToInbox;

    public function __construct(private readonly InspectionCase $case) {}

    public function broadcastType(): string
    {
        return 'case.assigned';
    }

    /**
     * @return array{type: string, case_id: int, reference_no: string, title: string, property_address: ?string, priority: string, planned_at: ?string, message: string}
     */
    protected function payload(): array
    {
        return [
            'type' => 'case.assigned',
            'case_id' => $this->case->id,
            'reference_no' => $this->case->reference_no,
            'title' => $this->case->title,
            'property_address' => $this->case->property_address,
            'priority' => $this->case->priority->value,
            'planned_at' => $this->case->planned_at?->toISOString(),
            'message' => "{$this->case->reference_no} — {$this->case->title} is available for you to accept.",
        ];
    }
}
