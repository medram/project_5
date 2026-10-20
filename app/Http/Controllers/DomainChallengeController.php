<?php

namespace App\Http\Controllers;

use App\Models\CustomDomain;
use App\Services\Domains\PublicLinkHostResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DomainChallengeController extends Controller
{
    public function __construct(private readonly PublicLinkHostResolver $hosts) {}

    public function __invoke(Request $request, string $token): Response
    {
        $hostname = $this->hosts->customHostnameFromRequest($request);

        abort_unless($hostname !== null, 404);

        $domain = CustomDomain::query()
            ->where('hostname', $hostname)
            ->where('verification_token', $token)
            ->firstOrFail();

        return response($domain->verification_token, 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
