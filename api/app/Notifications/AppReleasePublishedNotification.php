<?php

namespace App\Notifications;

use App\Models\AppRelease;
use App\Notifications\Concerns\BroadcastsToInbox;
use Illuminate\Notifications\Notification;

class AppReleasePublishedNotification extends Notification
{
    use BroadcastsToInbox;

    public function __construct(private readonly AppRelease $release) {}

    public function broadcastType(): string
    {
        return 'app-release.published';
    }

    /**
     * @return array{type: string, version_name: string, version_code: int, is_mandatory: bool, release_notes: ?string, message: string}
     */
    protected function payload(): array
    {
        return [
            'type' => 'app-release.published',
            'version_name' => $this->release->version_name,
            'version_code' => $this->release->version_code,
            'is_mandatory' => (bool) $this->release->is_mandatory,
            'release_notes' => $this->release->release_notes,
            'message' => $this->release->is_mandatory
                ? "Version {$this->release->version_name} is a required update — install it to keep using the app."
                : "Version {$this->release->version_name} of the app is available.",
        ];
    }
}
