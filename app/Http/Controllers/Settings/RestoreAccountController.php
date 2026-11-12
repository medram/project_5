<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\RestoreAccountRequest;
use App\Services\Accounts\RestoreAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RestoreAccountController extends Controller
{
    public function show(Request $request): Response
    {
        return Inertia::render('account/restore', [
            'purgeAfter' => $request->user()->purge_after?->toISOString(),
        ]);
    }

    public function store(RestoreAccountRequest $request, RestoreAccount $restore): RedirectResponse
    {
        $restore->restore($request->user());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Your account has been restored.'),
        ]);

        return to_route('dashboard');
    }
}
