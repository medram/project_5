<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SessionController extends Controller
{
    public function destroyOthers(Request $request): RedirectResponse
    {
        $user = $request->user();
        $currentId = $request->session()->getId();

        DB::table('sessions')
            ->where('user_id', $user->getKey())
            ->where('id', '!=', $currentId)
            ->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Other sessions were signed out.')]);

        return back();
    }

    public function revokeTokens(Request $request): RedirectResponse
    {
        $request->user()->tokens()->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('All API tokens were revoked.')]);

        return back();
    }
}
