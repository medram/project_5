<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnlockLinkRequest;
use App\Models\Link;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LinkAccessController extends Controller
{
    public function __invoke(UnlockLinkRequest $request, string $slug): RedirectResponse
    {
        $link = Link::query()
            ->where('slug', $slug)
            ->firstOrFail();

        abort_unless($link->isAvailableAt(now()) && $link->requiresAccessCode(), 404);

        if (! Hash::check((string) $request->validated('access_code'), (string) $link->access_code)) {
            throw ValidationException::withMessages([
                'access_code' => 'The access code is incorrect.',
            ]);
        }

        $request->session()->put("unlocked-links.{$link->id}", true);

        return to_route('links.redirect', ['slug' => $link->slug]);
    }
}
