<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectFreePlanRequest;
use App\Models\Plan;
use App\Services\Billing\AccountUsage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(private readonly AccountUsage $usage) {}

    public function index(Request $request): Response
    {
        $subscription = $this->usage->subscriptionFor($request->user());

        return Inertia::render('billing/index', [
            'subscription' => [
                'status' => $subscription->status,
                'source' => $subscription->source,
                'plan' => $this->planData($subscription->plan),
            ],
            'plans' => Plan::query()
                ->where('is_active', true)
                ->orderBy('monthly_price')
                ->get()
                ->map(fn (Plan $plan): array => $this->planData($plan))
                ->values()
                ->all(),
            'usage' => $this->usage->for($request->user()),
        ]);
    }

    public function selectFree(SelectFreePlanRequest $request): RedirectResponse
    {
        $freePlan = Plan::query()->where('code', $request->validated('plan'))->sole();
        $subscription = $this->usage->subscriptionFor($request->user());

        $subscription->update([
            'plan_id' => $freePlan->id,
            'status' => 'active',
            'source' => 'manual',
        ]);

        return to_route('billing.index');
    }

    /**
     * @return array{code: string, name: string, monthlyPrice: int, currency: string, limits: array<string, int|null>}
     */
    private function planData(Plan $plan): array
    {
        return [
            'code' => $plan->code,
            'name' => $plan->name,
            'monthlyPrice' => $plan->monthly_price,
            'currency' => $plan->currency,
            'limits' => [
                'links' => $plan->link_limit,
                'domains' => $plan->domain_limit,
                'pixels' => $plan->pixel_limit,
                'webhooks' => $plan->webhook_limit,
                'linkPages' => $plan->link_page_limit,
            ],
        ];
    }
}
