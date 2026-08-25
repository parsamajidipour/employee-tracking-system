<?php

namespace App\Notifications;

use App\Models\InspectionCase;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class CaseCreatedNotification extends Notification
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

    public function broadcastType(): string
    {
        return 'case.created';
    }

    /**
     * @return array{case_id: int, reference_no: string, title: string, property_address: ?string, priority: string}
     */
    private function payload(): array
    {
        return [
            'case_id' => $this->case->id,
            'reference_no' => $this->case->reference_no,
            'title' => $this->case->title,
            'property_address' => $this->case->property_address,
            'priority' => $this->case->priority->value,
        ];
    }
}
