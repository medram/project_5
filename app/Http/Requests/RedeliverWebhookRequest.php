<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Foundation\Http\FormRequest;

class RedeliverWebhookRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $webhook = $this->route('webhook');
        $delivery = $this->route('delivery');

        abort_unless(
            $user instanceof User
                && $webhook instanceof Webhook
                && $delivery instanceof WebhookDelivery,
            404,
        );

        $workspace = app(WorkspaceContext::class)->currentFor($user);
        abort_unless((int) $webhook->workspace_id === (int) $workspace->getKey(), 404);
        abort_unless((int) $delivery->webhook_id === (int) $webhook->getKey(), 404);

        return $user->can('update', $webhook);
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [];
    }
}
