<?php

namespace App\Http\Controllers;

use App\Http\Requests\SelectFreePlanRequest;
use App\Models\Plan;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class BillingController extends Controller
{
    public function __construct(private readonly WorkspaceUsage $usage) {}

    public function index(Request $request, WorkspaceContext $context): Response
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('viewBilling', $workspace);
        $subscription = $this->usage->subscriptionFor($workspace)->load('plan');

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
            'canManageBilling' => Gate::allows('manageBilling', $workspace),
            'usage' => $this->usage->for($workspace),
        ]);
    }

    public function selectFree(SelectFreePlanRequest $request, WorkspaceContext $context): RedirectResponse
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('manageBilling', $workspace);
        $freePlan = Plan::query()->where('code', $request->validated('plan'))->sole();
        $this->usage->assignPlan($workspace, $freePlan);

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
