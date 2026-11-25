<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Services\Billing\PaymentCheckout;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PaymentCheckoutController extends Controller
{
    public function abandon(Request $request, WorkspaceContext $context, PaymentCheckout $checkout): RedirectResponse
    {
        $workspace = $context->currentFor($request->user());
        Gate::authorize('manageBilling', $workspace);
        $checkout->abandon($workspace);

        return to_route('billing.index');
    }
}
