<?php

namespace App\Http\Controllers;

use App\Http\Requests\UnlockLinkRequest;
use App\Services\Domains\PublicLinkHostResolver;
use App\Services\Links\LinkAccessUnlocks;
use Illuminate\Http\RedirectResponse;
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

        $result = $this->unlocks->attempt(
            $request,
            $link,
            (string) $request->validated('access_code'),
        );

        abort_if($result === null, 429);

        if (! $result) {
            throw ValidationException::withMessages([
                'access_code' => 'The access code is incorrect.',
            ]);
        }

        return redirect()->to($this->hosts->canonicalUrl($link));
    }
}
