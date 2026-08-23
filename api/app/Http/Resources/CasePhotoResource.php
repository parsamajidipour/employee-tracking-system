<?php

namespace App\Http\Resources;

use App\Models\CasePhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CasePhoto
 */
class CasePhotoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'url' => route('case-photos.show', $this->id),
            'is_gps_verified' => $this->is_gps_verified,
            'distance_from_case_m' => round($this->distance_from_case_m, 1),
            'captured_at' => $this->captured_at?->toISOString(),
        ];
    }
}
