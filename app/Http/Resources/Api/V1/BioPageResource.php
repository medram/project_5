<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BioPageResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'handle' => $this->resource->handle,
            'display_name' => $this->resource->display_name,
            'bio' => $this->resource->bio,
            'is_published' => (bool) $this->resource->is_published,
            'appearance' => $this->resource->appearance,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
