<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\PlanStripePrice;
use App\Models\User;
use App\Services\Billing\StripePlanPrices;
use App\Services\Platform\AdminAudit;
use App\Services\Platform\PlatformAuthorization;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Stripe\Price;
use Throwable;

class AdminPlanPriceController extends Controller
{
    public function __construct(
        private readonly StripePlanPrices $prices,
        private readonly PlatformAuthorization $authorization,
        private readonly AdminAudit $audit,
    ) {}

    public function sync(Request $request, Plan $plan, string $interval): RedirectResponse
    {
        $data = $request->validate([
            'price_id' => ['required', 'string', 'max:255'],
            'expected_current_price_id' => ['present', 'nullable', 'string', 'max:255'],
        ]);
        return $this->change($request, $plan, $interval, $data['expected_current_price_id'], $data['price_id']);
    }

    public function destroy(Request $request, Plan $plan, string $interval): RedirectResponse
    {
        $data = $request->validate(['expected_current_price_id' => ['present', 'nullable', 'string', 'max:255']]);
        return $this->change($request, $plan, $interval, $data['expected_current_price_id'], null);
    }

    private function change(Request $request, Plan $plan, string $interval, ?string $expected, ?string $newId): RedirectResponse
    {
        abort_unless(in_array($interval, ['month', 'year'], true), 404);
        if ($plan->code === 'free') {
            throw ValidationException::withMessages(['price_id' => 'The free plan cannot have a Stripe price.']);
        }
        $column = $interval === 'month' ? 'stripe_monthly_price_id' : 'stripe_yearly_price_id';
        $amountColumn = $interval === 'month' ? 'monthly_price' : 'annual_price';
        if ($plan->stripePriceIdFor($interval) !== $expected) {
            throw ValidationException::withMessages(['expected_current_price_id' => 'The current price has changed. Refresh and try again.']);
        }

        $newPrice = $newId === null ? null : $this->retrieve($newId, $plan, $interval, true);
        $oldPrice = null;
        if ($expected !== null && $expected !== $newId && $this->referenced($expected)) {
            $old = PlanStripePrice::query()->where('stripe_price_id', $expected)->first();
            if ($old === null || $old->verified_at === null || $old->unit_amount === null) {
                $oldPrice = $this->retrieve($expected, $plan, $interval, false);
            }
        }

        try {
            DB::transaction(function () use ($request, $plan, $interval, $expected, $newId, $newPrice, $oldPrice, $column, $amountColumn): void {
                $actor = User::query()->lockForUpdate()->findOrFail($request->user()->getKey());
                abort_unless($this->authorization->allows($actor, PlatformPermission::AdminAccess) && $this->authorization->allows($actor, PlatformPermission::AdminBillingManage), 403);
                $locked = Plan::query()->lockForUpdate()->findOrFail($plan->getKey());
                if ($locked->stripePriceIdFor($interval) !== $expected || strtolower($locked->currency) !== strtolower($plan->currency)) {
                    throw ValidationException::withMessages(['expected_current_price_id' => 'The current price has changed. Refresh and try again.']);
                }
                if ($expected !== null && $expected !== $newId) {
                    $old = PlanStripePrice::query()->where('stripe_price_id', $expected)->lockForUpdate()->first();
                    if ($old !== null && ($old->plan_id !== $locked->id || $old->interval !== $interval)) {
                        throw ValidationException::withMessages(['price_id' => 'The current price belongs to another plan or interval.']);
                    }
                    if ($oldPrice !== null) {
                        $this->saveMapping($locked, $interval, $oldPrice);
                    } elseif (($old === null || $old->verified_at === null || $old->unit_amount === null) && $this->referenced($expected)) {
                        throw ValidationException::withMessages(['price_id' => 'The previous price must be verified before changing it.']);
                    }
                }
                if ($newPrice !== null) {
                    $this->saveMapping($locked, $interval, $newPrice);
                }
                $locked->forceFill([$column => $newId, $amountColumn => $newPrice?->unit_amount ?? 0])->save();
                $this->audit->record($actor, PlatformPermission::AdminBillingManage, $newId === null ? 'clear_plan_price' : 'sync_plan_price', $locked, null, ['interval' => $interval, 'mapped' => $newId !== null]);
            });
        } catch (QueryException $exception) {
            // A concurrent sync may have claimed the unique provider ID.
            if ($newId !== null && PlanStripePrice::query()->where('stripe_price_id', $newId)->exists()) {
                throw ValidationException::withMessages(['price_id' => 'This Stripe price is already assigned to another plan or interval.']);
            }
            throw $exception;
        }
        return back(fallback: route('admin.plans.index'));
    }

    private function retrieve(string $id, Plan $plan, string $interval, bool $mustBeActive): Price
    {
        try {
            $price = $this->prices->retrieve($id);
        } catch (Throwable) {
            throw ValidationException::withMessages(['price_id' => 'Unable to retrieve the Stripe price. Check the Stripe configuration and try again.']);
        }
        if ($price->id !== $id || ($mustBeActive && $price->active !== true)
            || $price->type !== 'recurring' || $price->billing_scheme !== 'per_unit'
            || ! is_int($price->unit_amount) || $price->unit_amount < 0
            || strtolower((string) $price->currency) !== strtolower($plan->currency)
            || $price->recurring === null || $price->recurring->interval !== $interval
            || $price->recurring->interval_count !== 1 || $price->recurring->usage_type !== 'licensed') {
            throw ValidationException::withMessages(['price_id' => 'The Stripe price must be a matching per-unit recurring price in the plan currency.']);
        }
        return $price;
    }

    private function referenced(string $id): bool
    {
        return DB::table('subscriptions')->where('stripe_price', $id)->exists()
            || DB::table('subscription_items')->where('stripe_price', $id)->exists();
    }

    private function saveMapping(Plan $plan, string $interval, Price $price): void
    {
        $mapping = PlanStripePrice::query()->where('stripe_price_id', $price->id)->lockForUpdate()->first();
        if ($mapping !== null && ($mapping->plan_id !== $plan->id || $mapping->interval !== $interval)) {
            throw ValidationException::withMessages(['price_id' => 'This Stripe price is already assigned to another plan or interval.']);
        }
        $mapping ??= new PlanStripePrice(['plan_id' => $plan->id, 'stripe_price_id' => $price->id, 'interval' => $interval]);
        $mapping->fill(['currency' => strtolower($price->currency), 'unit_amount' => $price->unit_amount, 'verified_at' => now()])->save();
    }
}
