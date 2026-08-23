<?php

namespace App\Http\Resources;

use App\Models\CaseStatusEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CaseStatusEvent
 */
class CaseStatusEventResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor_name' => $this->whenLoaded('actor', fn () => $this->actor?->name),
            'from_status' => $this->from_status?->value,
            'to_status' => $this->to_status->value,
            'note' => $this->note,
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
