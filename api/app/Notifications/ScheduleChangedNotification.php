<?php

namespace App\Notifications;

use App\Notifications\Concerns\BroadcastsToInbox;
use Illuminate\Notifications\Notification;

class ScheduleChangedNotification extends Notification
{
    use BroadcastsToInbox;

    /**
     * @param  list<string>  $shiftNames
     */
    public function __construct(
        private readonly array $shiftNames,
        private readonly ?string $actorName,
    ) {}

    public function broadcastType(): string
    {
        return 'schedule.changed';
    }

    /**
     * @return array{type: string, shift_names: list<string>, actor_name: ?string, message: string}
     */
    protected function payload(): array
    {
        return [
            'type' => 'schedule.changed',
            'shift_names' => $this->shiftNames,
            'actor_name' => $this->actorName,
            'message' => $this->message(),
        ];
    }

    private function message(): string
    {
        $who = $this->actorName ?? 'Management';

        if ($this->shiftNames === []) {
            return "{$who} removed every shift from your schedule — you are no longer tracked.";
        }

        return "{$who} updated your schedule: ".implode(', ', $this->shiftNames).'.';
    }
}
