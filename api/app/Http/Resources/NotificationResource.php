<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\DatabaseNotification;

/**
 * @mixin DatabaseNotification
 */
class NotificationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = is_array($this->data) ? $this->data : [];

        return [
            'id' => $this->id,
            'type' => $data['type'] ?? class_basename($this->type),
            'message' => $this->localizedMessage($data),
            'case_id' => $data['case_id'] ?? null,
            'reference_no' => $data['reference_no'] ?? null,
            'version_code' => $data['version_code'] ?? null,
            'version_name' => $data['version_name'] ?? null,
            'is_mandatory' => $data['is_mandatory'] ?? false,
            'read_at' => $this->read_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function localizedMessage(array $data): ?string
    {
        $type = $data['type'] ?? null;

        if ($type === 'case.assigned') {
            return __('notifications.case_assigned', [
                'reference' => $data['reference_no'] ?? '',
                'title' => $data['title'] ?? '',
            ]);
        }

        if ($type === 'case.created') {
            return __('notifications.case_created', [
                'reference' => $data['reference_no'] ?? '',
                'title' => $data['title'] ?? '',
            ]);
        }

        if ($type === 'case.status-changed') {
            if (($data['status'] ?? null) === 'overdue') {
                return __('notifications.case_overdue', [
                    'reference' => $data['reference_no'] ?? '',
                    'title' => $data['title'] ?? '',
                ]);
            }

            return __('notifications.case_status', [
                'actor' => $data['actor_name'] ?? __('notifications.someone'),
                'action' => __('notifications.status.'.($data['status'] ?? 'pending')),
                'reference' => $data['reference_no'] ?? '',
                'title' => $data['title'] ?? '',
            ]);
        }

        if ($type === 'app-release.published') {
            return __(($data['is_mandatory'] ?? false)
                ? 'notifications.release_required'
                : 'notifications.release_available', [
                    'version' => $data['version_name'] ?? '',
                ]);
        }

        if ($type === 'device.revoked') {
            return __('notifications.device_revoked', [
                'actor' => $data['actor_name'] ?? __('notifications.management'),
                'device' => $data['device_name'] ?? __('notifications.your_device'),
            ]);
        }

        if ($type === 'schedule.changed') {
            $actor = $data['actor_name'] ?? __('notifications.management');
            $shifts = is_array($data['shift_names'] ?? null) ? $data['shift_names'] : [];

            return $shifts === []
                ? __('notifications.schedule_removed', ['actor' => $actor])
                : __('notifications.schedule_updated', [
                    'actor' => $actor,
                    'shifts' => implode(', ', $shifts),
                ]);
        }

        return is_string($data['message'] ?? null) ? $data['message'] : null;
    }
}
