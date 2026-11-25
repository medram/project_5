<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PlatformPermission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AdminPayPalPlanRequest;
use App\Models\Plan;
use App\Models\PlanPayPalPrice;
use App\Models\User;
use App\Services\Billing\PayPalPlanVerifier;
use App\Services\Platform\AdminAudit;
use App\Services\Platform\PlatformAuthorization;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AdminPayPalPlanController extends Controller
{
    public function __construct(
        private readonly PayPalPlanVerifier $paypal,
        private readonly PlatformAuthorization $authorization,
        private readonly AdminAudit $audit,
    ) {}

    public function sync(AdminPayPalPlanRequest $request, Plan $plan, string $interval): RedirectResponse
    {
        $data = $request->validated();

        return $this->change($request, $plan, $interval, $data['expected_current_plan_id'], $data['plan_id']);
    }

    public function destroy(AdminPayPalPlanRequest $request, Plan $plan, string $interval): RedirectResponse
    {
        $data = $request->validated();

        return $this->change($request, $plan, $interval, $data['expected_current_plan_id'], null);
    }

    private function change(AdminPayPalPlanRequest $request, Plan $plan, string $interval, ?string $expected, ?string $newId): RedirectResponse
    {
        abort_unless(in_array($interval, ['month', 'year'], true), 404);
        if ($plan->code === 'free') {
            throw ValidationException::withMessages(['plan_id' => 'The free plan cannot have a PayPal plan.']);
        }
        if ($plan->paypalPlanIdFor($interval) !== $expected) {
            throw ValidationException::withMessages(['expected_current_plan_id' => 'The current plan has changed. Refresh and try again.']);
        }
        $amount = $newId === null ? null : $this->paypal->amount($newId, $plan, $interval);
        $column = $interval === 'month' ? 'paypal_monthly_plan_id' : 'paypal_yearly_plan_id';
        $amountColumn = $interval === 'month' ? 'monthly_price' : 'annual_price';
        try {
            DB::transaction(function () use ($request, $plan, $interval, $expected, $newId, $amount, $column, $amountColumn): void {
                $actor = User::query()->lockForUpdate()->whereKey($request->user()->getKey())->firstOrFail();
                abort_unless($this->authorization->allows($actor, PlatformPermission::AdminAccess) && $this->authorization->allows($actor, PlatformPermission::AdminBillingManage), 403);
                $locked = Plan::query()->lockForUpdate()->whereKey($plan->getKey())->firstOrFail();
                if ($locked->paypalPlanIdFor($interval) !== $expected || strtolower($locked->currency) !== strtolower($plan->currency)) {
                    throw ValidationException::withMessages(['expected_current_plan_id' => 'The current plan has changed. Refresh and try again.']);
                }
                if ($newId !== null) {
                    $stripeId = $locked->stripePriceIdFor($interval);
                    $stripe = $stripeId === null ? null : $locked->stripePrices()->where('stripe_price_id', $stripeId)->whereNotNull('verified_at')->first();
                    if ($stripe !== null && ($stripe->unit_amount !== $amount || strtolower($stripe->currency) !== strtolower($locked->currency))) {
                        throw ValidationException::withMessages(['plan_id' => 'The PayPal amount must match the verified Stripe price.']);
                    }
                    $mapping = PlanPayPalPrice::query()->where('paypal_plan_id', $newId)->lockForUpdate()->first();
                    if ($mapping !== null && ($mapping->plan_id !== $locked->id || $mapping->interval !== $interval)) {
                        throw ValidationException::withMessages(['plan_id' => 'This PayPal plan is already assigned to another plan or interval.']);
                    }
                    $mapping ??= new PlanPayPalPrice(['plan_id' => $locked->id, 'paypal_plan_id' => $newId, 'interval' => $interval]);
                    if ($mapping->exists && ($mapping->unit_amount !== $amount || strtolower($mapping->currency) !== strtolower($locked->currency))) {
                        throw ValidationException::withMessages(['plan_id' => 'The verified PayPal plan amount has changed. Use a new PayPal plan instead.']);
                    }
                    $mapping->fill(['currency' => strtolower($locked->currency), 'unit_amount' => $amount, 'verified_at' => now()])->save();
                }
                $updates = [$column => $newId];
                if ($newId !== null && $stripe === null) {
                    $updates[$amountColumn] = $amount;
                }
                $locked->forceFill($updates)->save();
                $this->audit->record($actor, PlatformPermission::AdminBillingManage, $newId === null ? 'clear_paypal_plan' : 'sync_paypal_plan', $locked, null, ['interval' => $interval, 'mapped' => $newId !== null]);
            });
        } catch (QueryException $exception) {
            if ($newId !== null && PlanPayPalPrice::query()->where('paypal_plan_id', $newId)->exists()) {
                throw ValidationException::withMessages(['plan_id' => 'This PayPal plan is already assigned to another plan or interval.']);
            }
            throw $exception;
        }

        return back(fallback: route('admin.plans.index'));
    }
}
