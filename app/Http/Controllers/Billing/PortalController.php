<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\StripeReadiness;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class PortalController extends Controller
{
    public function __invoke(Request $request, WorkspaceContext $context, StripeReadiness $readiness): RedirectResponse
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('manageBilling', $workspace);

        if (! $readiness->configured()) {
            throw ValidationException::withMessages([
                'billing' => 'Stripe billing is not configured. Review the billing setup checklist.',
            ]);
        }

        return $workspace->redirectToBillingPortal(route('billing.index'));
    }
}