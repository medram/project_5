<?php

namespace App\Http\Controllers;

use App\Services\Billing\PlanCatalogPresenter;
use App\Services\Billing\WorkspaceUsage;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PricingController extends Controller
{
    public function __invoke(
        Request $request,
        WorkspaceContext $context,
        PlanCatalogPresenter $plans,
        WorkspaceUsage $usage,
    ): Response {
        $currentPlanCode = null;
        $canManageBilling = false;
        $user = $request->user();

        if ($user !== null) {
            $workspace = $context->currentFor($user);
            $currentPlanCode = $usage->planFor($workspace)->code;
            $canManageBilling = Gate::allows('manageBilling', $workspace);
        }

        return Inertia::render('pricing/index', [
            'plans' => $plans->all(),
            'currentPlanCode' => $currentPlanCode,
            'canManageBilling' => $canManageBilling,
            'billingPath' => route('billing.index', absolute: false),
            'loginPath' => route('login', absolute: false),
        ]);
    }
}
