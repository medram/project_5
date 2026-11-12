<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\PlanCatalogPresenter;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CheckoutResultController extends Controller
{
    public function __construct(
        private readonly WorkspaceUsage $usage,
        private readonly PlanCatalogPresenter $plans,
    ) {}

    public function success(Request $request, WorkspaceContext $context): Response
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('viewBilling', $workspace);
        $effective = $this->usage->effectivePlanFor($workspace);

        return Inertia::render('billing/checkout-result', [
            'status' => 'success',
            'processing' => $effective['source'] !== 'stripe',
            'sessionReturned' => $request->filled('session_id'),
            'effectivePlan' => $this->plans->data($effective['plan']),
            'effectiveSource' => $effective['source'],
            'provider' => $effective['subscription'] === null ? null : [
                'status' => $effective['subscription']->stripe_status,
                'endsAt' => $effective['subscription']->ends_at?->toISOString(),
            ],
        ]);
    }

    public function cancel(Request $request, WorkspaceContext $context): Response
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('viewBilling', $workspace);
        $effective = $this->usage->effectivePlanFor($workspace);

        return Inertia::render('billing/checkout-result', [
            'status' => 'cancelled',
            'processing' => false,
            'sessionReturned' => false,
            'effectivePlan' => $this->plans->data($effective['plan']),
            'effectiveSource' => $effective['source'],
            'provider' => null,
        ]);
    }
}
