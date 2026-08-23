<?php

namespace App\Notifications;

use App\Models\InspectionCase;
use Illuminate\Broadcasting\Channel;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CaseAssignedNotification extends Notification
{
    public function __construct(private readonly InspectionCase $case) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return $this->payload();
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->payload());
    }

    public function broadcastOn(): Channel
    {
        return new Channel('App.Models.User.'.$this->case->assigned_to);
    }

    public function broadcastType(): string
    {
        return 'case.assigned';
    }

    /**
     * @return array{case_id: int, reference_no: string, title: string, property_address: ?string, priority: string, planned_at: ?string}
     */
    private function payload(): array
    {
        return [
            'case_id' => $this->case->id,
            'reference_no' => $this->case->reference_no,
            'title' => $this->case->title,
            'property_address' => $this->case->property_address,
            'priority' => $this->case->priority->value,
            'planned_at' => $this->case->planned_at?->toISOString(),
        ];
    }
}
