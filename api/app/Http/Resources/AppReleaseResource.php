<?php

namespace App\Http\Resources;

use App\Models\AppRelease;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AppRelease
 */
class AppReleaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version_code' => $this->version_code,
            'version_name' => $this->version_name,
            'release_notes' => $this->release_notes,
            'is_mandatory' => $this->is_mandatory,
            'file_size' => $this->file_size,
            'download_url' => route('app-releases.download', $this->id),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
