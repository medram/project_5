<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AnalyticsEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $resource = match (true) {
            $this->resource->link_id !== null => ['type' => 'link', 'id' => (int) $this->resource->link_id],
            $this->resource->bio_page_id !== null => ['type' => 'bio_page', 'id' => (int) $this->resource->bio_page_id],
            $this->resource->link_page_id !== null => ['type' => 'link_page', 'id' => (int) $this->resource->link_page_id],
            default => null,
        };

        return [
            'id' => (int) $this->resource->getKey(),
            'uuid' => $this->resource->uuid,
            'event_name' => $this->resource->event_name,
            'occurred_at' => $this->resource->occurred_at?->toISOString(),
            'resource' => $resource,
        ];
    }
}
