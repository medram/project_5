<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\StoreCustomAnalyticsEventRequest;
use App\Http\Resources\Api\V1\AnalyticsEventResource;
use App\Models\BioPage;
use App\Models\Link;
use App\Models\LinkPage;
use App\Models\User;
use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class AnalyticsEventController extends ApiController
{
    public function __construct(private readonly AnalyticsTracker $tracker) {}

    public function store(StoreCustomAnalyticsEventRequest $request): JsonResponse
    {
        $workspace = $this->workspace($request);
        $attributes = $request->validated();
        $actor = $request->user();
        abort_unless($actor instanceof User, 401);

        $declared = $workspace->analyticsEventDefinitions()
            ->where('event_name', $attributes['event_name'])
            ->where('is_active', true)
            ->exists();
        if (! $declared) {
            throw ValidationException::withMessages([
                'event_name' => 'The event name is not actively declared in this workspace.',
            ]);
        }

        $resource = match ($attributes['resource_type']) {
            'link' => $workspace->links()->findOrFail($attributes['resource_id']),
            'bio_page' => $workspace->bioPages()->findOrFail($attributes['resource_id']),
            'link_page' => $workspace->linkPages()->findOrFail($attributes['resource_id']),
        };
        $references = match (true) {
            $resource instanceof Link => ['link' => $resource],
            $resource instanceof BioPage => ['bioPage' => $resource],
            $resource instanceof LinkPage => ['linkPage' => $resource],
        };

        $event = $this->tracker->recordDeclaredApiEvent(
            $workspace,
            $attributes['event_name'],
            $actor,
            $references,
        );

        return (new AnalyticsEventResource($event))->response()->setStatusCode(201);
    }
}
