<?php

namespace App\Services\Billing;

use App\Models\PaymentCheckoutAttempt;
use App\Models\PayPalSubscription;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class PayPalSubscriptions
{
    public function __construct(private readonly PayPalClient $paypal) {}

    public function reconcile(string $id): void
    {
        $subscription = PayPalSubscription::query()->where('paypal_subscription_id', $id)->first();
        if ($subscription === null) {
            return;
        }

        DB::transaction(function () use ($subscription, $id): void {
            Workspace::query()->whereKey($subscription->workspace_id)->lockForUpdate()->firstOrFail();
            $locked = PayPalSubscription::query()->with('price')->lockForUpdate()->findOrFail($subscription->id);
            if (in_array($locked->status, ['CANCELLED', 'EXPIRED'], true)) {
                return;
            }
            $remote = $this->paypal->subscription($id);
            if (($remote['id'] ?? null) !== $id
                || ($remote['custom_id'] ?? null) !== $locked->custom_id
                || ($remote['plan_id'] ?? null) !== $locked->price->paypal_plan_id) {
                throw new RuntimeException('PayPal subscription does not match the local checkout.');
            }

            $status = $remote['status'] ?? null;
            if (! in_array($status, ['APPROVAL_PENDING', 'APPROVED', 'ACTIVE', 'SUSPENDED', 'CANCELLED', 'EXPIRED'], true)) {
                throw new RuntimeException('Unexpected PayPal subscription status.');
            }

            $billing = is_array($remote['billing_info'] ?? null) ? $remote['billing_info'] : [];
            $last = is_array($billing['last_payment'] ?? null) ? $billing['last_payment'] : [];
            $amount = is_array($last['amount'] ?? null) ? $last['amount'] : [];
            $lastPaidAt = $locked->last_paid_at;
            if ($last !== []) {
                $value = $amount['value'] ?? null;
                $cents = is_string($value) && preg_match('/^(0|[1-9][0-9]{0,14})(?:\.([0-9]{1,2}))?$/D', $value, $matches)
                    ? (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0')
                    : null;
                $lastPaidAt = is_string($last['time'] ?? null)
                    && strtoupper((string) ($amount['currency_code'] ?? '')) === strtoupper($locked->price->currency)
                    && $cents !== null && $cents >= $locked->price->unit_amount
                    ? Carbon::parse($last['time']) : null;
            }
            if ($status === 'ACTIVE' && (int) ($billing['failed_payments_count'] ?? 0) > 0) {
                $status = 'PAYMENT_FAILED';
            }
            $locked->update([
                'status' => $status,
                'last_paid_at' => $lastPaidAt,
                'next_billing_at' => is_string($billing['next_billing_time'] ?? null)
                    ? Carbon::parse($billing['next_billing_time']) : null,
            ]);

            if (! in_array($status, ['APPROVAL_PENDING', 'APPROVED'], true)) {
                PaymentCheckoutAttempt::query()->where('workspace_id', $locked->workspace_id)
                    ->where('provider', 'paypal')->where('reference', $locked->custom_id)->delete();
            }
        });
    }

    public function refresh(Workspace $workspace): void
    {
        $subscription = PayPalSubscription::query()->where('workspace_id', $workspace->id)
            ->whereIn('status', ['APPROVAL_PENDING', 'APPROVED', 'ACTIVE', 'SUSPENDED', 'PAYMENT_FAILED'])
            ->orderByDesc('id')->first();
        if ($subscription === null || ! is_string($subscription->paypal_subscription_id)) {
            throw ValidationException::withMessages(['subscription' => 'There is no PayPal subscription to refresh.']);
        }

        try {
            $this->reconcile($subscription->paypal_subscription_id);
        } catch (Throwable) {
            throw ValidationException::withMessages(['subscription' => 'PayPal status could not be refreshed. Please try again.']);
        }
    }

    public function cancel(Workspace $workspace): void
    {
        try {
            DB::transaction(function () use ($workspace): void {
                $locked = Workspace::query()->lockForUpdate()->findOrFail($workspace->id);
                $subscription = PayPalSubscription::query()->where('workspace_id', $locked->id)
                    ->whereIn('status', ['ACTIVE', 'SUSPENDED', 'PAYMENT_FAILED'])
                    ->orderByDesc('id')->lockForUpdate()->first();
                if ($subscription === null || ! is_string($subscription->paypal_subscription_id)) {
                    throw ValidationException::withMessages(['subscription' => 'There is no PayPal subscription to cancel.']);
                }
                $this->paypal->cancelSubscription($subscription->paypal_subscription_id, 'Customer cancelled the subscription');
                $subscription->update(['status' => 'CANCELLED', 'next_billing_at' => null]);
                PaymentCheckoutAttempt::query()->where('workspace_id', $locked->id)->where('provider', 'paypal')->delete();
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['subscription' => 'PayPal could not cancel the subscription. Please try again.']);
        }
    }
}
