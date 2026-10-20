<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnlockLinkRequest;
use App\Services\Domains\PublicLinkHostResolver;
use App\Services\Links\LinkAccessUnlocks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class LinkAccessController extends Controller
{
    public function __construct(
        private readonly PublicLinkHostResolver $hosts,
        private readonly LinkAccessUnlocks $unlocks,
    ) {}

    public function __invoke(UnlockLinkRequest $request, string $slug): RedirectResponse
    {
        $link = $this->hosts->resolveLink($request, $slug);

        abort_unless($link->isAvailableAt(now()) && $link->requiresAccessCode(), 404);

        abort_if($this->unlocks->tooManyFailures($request, $link), 429);

        if (! Hash::check((string) $request->validated('access_code'), (string) $link->access_code)) {
            $this->unlocks->recordFailure($request, $link);

            throw ValidationException::withMessages([
                'access_code' => 'The access code is incorrect.',
            ]);
        }

        $this->unlocks->clearFailures($request, $link);
        $this->unlocks->grant($request, $link);

        return redirect()->to($this->hosts->canonicalUrl($link));
    }
}
