<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ScopedAdminContent;
use App\Http\Requests\UpdateCustomDomainRequest;
use App\Models\CustomDomain;
use App\Services\Domains\DomainVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class AdminCustomDomainEditController extends Controller
{
    public function update(UpdateCustomDomainRequest $request, CustomDomain $customDomain, DomainVerificationService $verification): RedirectResponse
    {
        $domain = $customDomain->fresh();
        if ($request->validated('confirmation') !== $domain->hostname) {
            throw ValidationException::withMessages(['confirmation' => 'The hostname changed. Reload and confirm the current hostname.']);
        }

        $hostname = $request->validated('hostname');
        if ($hostname !== $domain->hostname) {
            $domain->hostname = $hostname;
            $verification->reissueToken($domain);
        }

        return redirect($request->attributes->get(ScopedAdminContent::ATTRIBUTE)['editor_url']);
    }
}
