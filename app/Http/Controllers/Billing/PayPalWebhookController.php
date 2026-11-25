<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\PayPalClient;
use App\Services\Billing\PayPalSubscriptions;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class PayPalWebhookController extends Controller
{
    public function __invoke(Request $request, PayPalClient $paypal, PayPalSubscriptions $subscriptions): Response
    {
        $event = $request->json()->all();
        if (! is_string($event['event_type'] ?? null) || ! is_array($event['resource'] ?? null)) {
            return response('Invalid webhook.', 400);
        }

        try {
            $verified = $paypal->verifyWebhook([
                'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
                'cert_url' => $request->header('PAYPAL-CERT-URL'),
                'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
                'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
                'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            ], $event);
            if (! $verified) {
                return response('Invalid signature.', 403);
            }

            $type = $event['event_type'];
            $id = str_starts_with($type, 'BILLING.SUBSCRIPTION.')
                ? ($event['resource']['id'] ?? null)
                : (str_starts_with($type, 'PAYMENT.SALE.') ? ($event['resource']['billing_agreement_id'] ?? null) : null);
            if (is_string($id) && $id !== '') {
                $subscriptions->reconcile($id);
            }
        } catch (Throwable $exception) {
            Log::warning('PayPal webhook processing failed.', ['exception' => $exception::class]);

            return response('Webhook processing failed.', 503);
        }

        return response('', 204);
    }
}
