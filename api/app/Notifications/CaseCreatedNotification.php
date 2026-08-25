<?php

namespace App\Notifications;

use App\Models\InspectionCase;
use App\Notifications\Concerns\BroadcastsToInbox;
use Illuminate\Notifications\Notification;

class CaseCreatedNotification extends Notification
{
    use BroadcastsToInbox;

    public function __construct(private readonly InspectionCase $case) {}

    public function broadcastType(): string
    {
        return 'case.created';
    }

    /**
     * @return array{type: string, case_id: int, reference_no: string, title: string, property_address: ?string, priority: string, message: string}
     */
    protected function payload(): array
    {
        return [
            'type' => 'case.created',
            'case_id' => $this->case->id,
            'reference_no' => $this->case->reference_no,
            'title' => $this->case->title,
            'property_address' => $this->case->property_address,
            'priority' => $this->case->priority->value,
            'message' => "New case {$this->case->reference_no} — {$this->case->title}.",
        ];
    }
}
