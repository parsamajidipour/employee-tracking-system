<?php

namespace App\Http\Resources;

use App\Models\InspectionCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin InspectionCase
 */
class CaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'title' => $this->title,
            'property_address' => $this->property_address,
            'lat' => (float) $this->lat,
            'lng' => (float) $this->lng,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'assigned_to' => $this->assigned_to,
            'assignee_name' => $this->whenLoaded('assignee', fn () => $this->assignee?->name),
            'assigned_at' => $this->assigned_at?->toISOString(),
            'accepted_at' => $this->accepted_at?->toISOString(),
            'planned_at' => $this->planned_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toISOString(),
            'status_events' => CaseStatusEventResource::collection($this->whenLoaded('statusEvents')),
            'photos' => CasePhotoResource::collection($this->whenLoaded('photos')),
        ];
    }
}
