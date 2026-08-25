<?php

namespace App\Notifications;

use App\Notifications\Concerns\BroadcastsToInbox;
use Illuminate\Notifications\Notification;

class DeviceRevokedNotification extends Notification
{
    use BroadcastsToInbox;

    public function __construct(
        private readonly ?string $deviceName,
        private readonly ?string $actorName,
    ) {}

    public function broadcastType(): string
    {
        return 'device.revoked';
    }

    /**
     * @return array{type: string, device_name: ?string, actor_name: ?string, message: string}
     */
    protected function payload(): array
    {
        return [
            'type' => 'device.revoked',
            'device_name' => $this->deviceName,
            'actor_name' => $this->actorName,
            'message' => ($this->actorName ?? 'Management').' revoked '.($this->deviceName ?? 'your device').'. Sign in again on your phone to keep tracking.',
        ];
    }
}
