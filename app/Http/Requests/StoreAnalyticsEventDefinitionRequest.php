<?php

namespace App\Http\Requests;

use App\Models\AnalyticsEventDefinition;
use App\Models\User;
use App\Models\Workspace;
use App\Services\Analytics\AnalyticsTracker;
use App\Services\Workspaces\WorkspaceContext;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAnalyticsEventDefinitionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User
            && $user->can('manageIntegrations', $this->currentWorkspace());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('event_name'))) {
            $this->merge(['event_name' => trim($this->input('event_name'))]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $workspace = $this->currentWorkspace();

        return [
            'event_name' => [
                'required',
                'string',
                'max:64',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! is_string($value) || ! AnalyticsTracker::isValidCustomEventName($value)) {
                        $fail('Use a lowercase event name with letters, numbers, dots, underscores, or hyphens.');
                    }
                },
                Rule::unique(AnalyticsEventDefinition::class, 'event_name')
                    ->where('workspace_id', $workspace->id),
            ],
        ];
    }

    private function currentWorkspace(): Workspace
    {
        $user = $this->user();
        abort_unless($user instanceof User, 403);

        return app(WorkspaceContext::class)->currentFor($user);
    }
}
