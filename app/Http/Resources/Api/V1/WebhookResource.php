<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WebhookResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $workspace = $request->attributes->get('api_workspace');
        $canManage = $workspace instanceof Workspace
            && $request->user()?->can('manageIntegrations', $workspace);

        return [
            'id' => (int) $this->resource->getKey(),
            'name' => $this->resource->name,
            'url' => $canManage ? $this->resource->url : null,
            'events' => $this->resource->events,
            'is_active' => (bool) $this->resource->is_active,
            'revision' => (int) $this->resource->revision,
            'secret_rotated_at' => $this->resource->secret_rotated_at?->toISOString(),
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ];
    }
}
