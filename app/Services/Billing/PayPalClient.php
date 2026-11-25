<?php

namespace App\Services\Billing;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class PayPalClient
{
    private ?string $accessToken = null;

    /** @return array<string, mixed> */
    public function plan(string $id): array
    {
        return $this->request()->get($this->baseUrl().'/v1/billing/plans/'.rawurlencode($id))->throw()->json();
    }

    /** @return array<string, mixed> */
    public function webhook(string $id): array
    {
        return $this->request()->get($this->baseUrl().'/v1/notifications/webhooks/'.rawurlencode($id))->throw()->json();
    }

    /** @return array<string, mixed> */
    public function createSubscription(string $planId, string $customId, string $returnUrl, string $cancelUrl, string $requestId): array
    {
        return $this->request()->withHeaders(['PayPal-Request-Id' => $requestId])
            ->post($this->baseUrl().'/v1/billing/subscriptions', [
                'plan_id' => $planId,
                'custom_id' => $customId,
                'application_context' => [
                    'payment_method' => ['payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED'],
                    'shipping_preference' => 'NO_SHIPPING',
                    'user_action' => 'SUBSCRIBE_NOW',
                    'return_url' => $returnUrl,
                    'cancel_url' => $cancelUrl,
                ],
            ])->throw()->json();
    }

    /** @return array<string, mixed> */
    public function subscription(string $id): array
    {
        return $this->request()->get($this->baseUrl().'/v1/billing/subscriptions/'.rawurlencode($id))->throw()->json();
    }

    public function cancelSubscription(string $id, string $reason): void
    {
        $this->request()->post($this->baseUrl().'/v1/billing/subscriptions/'.rawurlencode($id).'/cancel', [
            'reason' => $reason,
        ])->throw();
    }

    /** @param array<string, mixed> $event
     * @param  array<string, string|null>  $headers
     */
    public function verifyWebhook(array $headers, array $event): bool
    {
        foreach (['auth_algo', 'cert_url', 'transmission_id', 'transmission_sig', 'transmission_time'] as $key) {
            if (! is_string($headers[$key] ?? null) || $headers[$key] === '') {
                return false;
            }
        }

        $webhookId = config('services.paypal.webhook_id');
        if (! is_string($webhookId) || $webhookId === '') {
            return false;
        }

        return $this->request()->post($this->baseUrl().'/v1/notifications/verify-webhook-signature', [
            ...$headers,
            'webhook_id' => $webhookId,
            'webhook_event' => $event,
        ])->throw()->json('verification_status') === 'SUCCESS';
    }

    private function request(): PendingRequest
    {
        if ($this->accessToken === null) {
            $token = Http::timeout(15)->acceptJson()->asForm()
                ->withBasicAuth((string) config('services.paypal.client_id'), (string) config('services.paypal.secret'))
                ->post($this->baseUrl().'/v1/oauth2/token', ['grant_type' => 'client_credentials'])
                ->throw()->json('access_token');

            if (! is_string($token) || $token === '') {
                throw new RuntimeException('PayPal did not provide an access token.');
            }

            $this->accessToken = $token;
        }

        return Http::timeout(15)->acceptJson()->asJson()->withToken($this->accessToken);
    }

    private function baseUrl(): string
    {
        return match (config('services.paypal.mode')) {
            'sandbox' => 'https://api-m.sandbox.paypal.com',
            'live' => 'https://api-m.paypal.com',
            default => throw new RuntimeException('Invalid PayPal environment.'),
        };
    }
}
