<?php

namespace App\Notifications;

use App\Enums\CaseStatus;
use App\Models\InspectionCase;
use App\Notifications\Concerns\BroadcastsToInbox;
use Illuminate\Notifications\Notification;

class CaseStatusChangedNotification extends Notification
{
    use BroadcastsToInbox;

    public function __construct(
        private readonly InspectionCase $case,
        private readonly CaseStatus $to,
        private readonly ?string $actorName,
        private readonly ?string $note = null,
    ) {}

    public function broadcastType(): string
    {
        return 'case.status-changed';
    }

    /**
     * @return array{type: string, case_id: int, reference_no: string, title: string, status: string, actor_name: ?string, note: ?string, message: string}
     */
    protected function payload(): array
    {
        return [
            'type' => 'case.status-changed',
            'case_id' => $this->case->id,
            'reference_no' => $this->case->reference_no,
            'title' => $this->case->title,
            'status' => $this->to->value,
            'actor_name' => $this->actorName,
            'note' => $this->note,
            'message' => $this->message(),
        ];
    }

    private function message(): string
    {
        $who = $this->actorName ?? 'Someone';
        $verb = match ($this->to) {
            CaseStatus::Accepted => 'accepted',
            CaseStatus::Rejected => 'rejected',
            CaseStatus::InProgress => 'started',
            CaseStatus::Completed => 'completed',
            CaseStatus::Cancelled => 'cancelled',
            CaseStatus::Pending => 'reopened',
        };

        return "{$who} {$verb} {$this->case->reference_no} — {$this->case->title}.";
    }
}
