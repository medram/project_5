<?php

namespace App\Http\Controllers;

use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QrCodeController extends Controller
{
    public function __construct(private readonly WorkspaceContext $workspaceContext) {}

    public function index(Request $request): Response
    {
        $workspace = $this->workspaceContext->currentFor($request->user());

        return Inertia::render('qr-codes/index', [
            'campaigns' => $workspace->qrCodes()
                ->with(['link' => fn ($query) => $query
                    ->where('links.workspace_id', $workspace->id)
                    ->select(['links.id', 'links.slug', 'links.title'])])
                ->withCount('scans')
                ->orderBy('id')
                ->get(),
            'availableLinks' => $workspace->links()
                ->orderBy('title')
                ->get(['id', 'slug', 'title', 'destination_url']),
        ]);
    }
}
