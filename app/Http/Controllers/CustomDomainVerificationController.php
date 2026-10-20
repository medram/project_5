<?php

namespace App\Http\Controllers;

use App\Models\CustomDomain;
use App\Services\Domains\DomainVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class CustomDomainVerificationController extends Controller
{
    public function __construct(private readonly DomainVerificationService $verification) {}

    public function verify(CustomDomain $customDomain): RedirectResponse
    {
        Gate::authorize('update', $customDomain);

        $this->verification->verify($customDomain);

        return to_route('custom-domains.index');
    }

    public function reissue(CustomDomain $customDomain): RedirectResponse
    {
        Gate::authorize('update', $customDomain);

        $this->verification->reissueToken($customDomain);

        return to_route('custom-domains.index');
    }
}
