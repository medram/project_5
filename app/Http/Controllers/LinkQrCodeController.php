<?php

namespace App\Http\Controllers;

use App\Models\Link;
use App\Services\Domains\PublicLinkHostResolver;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class LinkQrCodeController extends Controller
{
    public function __construct(private readonly PublicLinkHostResolver $hosts) {}

    public function __invoke(Link $link): Response
    {
        Gate::authorize('view', $link);

        $svg = (new Builder(
            writer: new SvgWriter,
            writerOptions: [],
            validateResult: false,
            data: $this->hosts->canonicalUrl($link),
            size: 320,
            margin: 12,
        ))->build()->getString();

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => "inline; filename=\"{$link->slug}.svg\"",
        ]);
    }
}
