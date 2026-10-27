<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LinkResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->resource->getKey(),
            'slug' => $this->resource->slug,
            'title' => $this->resource->title,
            'destination_url' => $this->resource->destination_url,
            'is_active' => (bool) $this->resource->is_active,
            'delivery_mode' => $this->resource->delivery_mode,
            'approval_status' => $this->resource->approval_status,
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
