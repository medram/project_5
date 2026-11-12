<?php

namespace App\Http\Controllers;

use App\Enums\PlatformPermission;
use App\Models\Plan;
use App\Models\User;
use App\Services\Platform\PlatformAuthorization;
use App\Services\Workspaces\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkspaceSearchController extends Controller
{
    public function __invoke(Request $request, WorkspaceContext $workspaces): JsonResponse
    {
        $query = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:100'],
        ])['q'];
        $pattern = '%'.addcslashes($query, '\\%_').'%';
        $escape = '\\';

        if ($request->user()->can('admin.search')) {
            $authorization = app(PlatformAuthorization::class);
            $results = [];

            if ($authorization->allows($request->user(), PlatformPermission::AdminUsersView)) {
                $results = [
                    ...User::query()
                        ->where(fn ($users) => $users->whereRaw('name LIKE ? ESCAPE ?', [$pattern, $escape])
                            ->orWhereRaw('email LIKE ? ESCAPE ?', [$pattern, $escape]))
                        ->orderBy('name')
                        ->limit(5)
                        ->get(['id', 'name', 'email'])
                        ->map(fn (User $user): array => [
                            'id' => (int) $user->id,
                            'type' => 'account',
                            'title' => $user->name,
                            'description' => $user->email,
                            'href' => route('admin.users.index', ['q' => $query], false),
                        ])
                        ->all(),
                ];
            }

            if ($authorization->allows($request->user(), PlatformPermission::AdminBillingView)) {
                $results = [
                    ...$results,
                    ...Plan::query()
                        ->where('is_active', true)
                        ->where(fn ($plans) => $plans
                            ->whereRaw('name LIKE ? ESCAPE ?', [$pattern, $escape])
                            ->orWhereRaw('code LIKE ? ESCAPE ?', [$pattern, $escape]))
                        ->orderBy('name')
                        ->limit(5)
                        ->get(['id', 'name'])
                        ->map(fn (Plan $plan): array => [
                            'id' => (int) $plan->id,
                            'type' => 'plan',
                            'title' => $plan->name,
                            'description' => 'Plan',
                            'href' => route('admin.users.index', ['q' => $query], false),
                        ])
                        ->all(),
                ];
            }

            return response()->json(['results' => $results]);
        }

        $workspace = $workspaces->currentFor($request->user());
        $canManageIntegrations = $request->user()->can('manageIntegrations', $workspace);

        $results = [
            ...$workspace->links()
                ->where(fn ($links) => $links->whereRaw('title LIKE ? ESCAPE ?', [$pattern, $escape])
                    ->orWhereRaw('slug LIKE ? ESCAPE ?', [$pattern, $escape]))
                ->orderBy('title')
                ->limit(5)
                ->get(['id', 'title', 'slug'])
                ->map(fn ($link): array => [
                    'id' => (int) $link->id,
                    'type' => 'link',
                    'title' => $link->title ?: "/{$link->slug}",
                    'description' => "/{$link->slug}",
                    'href' => route('links.index', ['search' => $query], false),
                ])
                ->all(),
            ...$workspace->folders()
                ->whereRaw('name LIKE ? ESCAPE ?', [$pattern, $escape])
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name'])
                ->map(fn ($folder): array => [
                    'id' => (int) $folder->id,
                    'type' => 'folder',
                    'title' => $folder->name,
                    'description' => 'Folder',
                    'href' => route('folders.index', ['search' => $query], false),
                ])
                ->all(),
            ...$workspace->bioPages()
                ->where(fn ($pages) => $pages->whereRaw('display_name LIKE ? ESCAPE ?', [$pattern, $escape])
                    ->orWhereRaw('handle LIKE ? ESCAPE ?', [$pattern, $escape]))
                ->orderBy('display_name')
                ->limit(5)
                ->get(['id', 'display_name', 'handle'])
                ->map(fn ($page): array => [
                    'id' => (int) $page->id,
                    'type' => 'bio_page',
                    'title' => $page->display_name,
                    'description' => "@{$page->handle}",
                    'href' => route('bio-pages.show', $page, false),
                ])
                ->all(),
            ...$workspace->linkPages()
                ->where(fn ($pages) => $pages->whereRaw('name LIKE ? ESCAPE ?', [$pattern, $escape])
                    ->orWhereRaw('title LIKE ? ESCAPE ?', [$pattern, $escape])
                    ->orWhereRaw('slug LIKE ? ESCAPE ?', [$pattern, $escape]))
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'slug'])
                ->map(fn ($page): array => [
                    'id' => (int) $page->id,
                    'type' => 'link_page',
                    'title' => $page->name,
                    'description' => "/pages/{$page->slug}",
                    'href' => route('link-pages.show', $page, false),
                ])
                ->all(),
            ...$workspace->qrCodes()
                ->with(['link' => fn ($links) => $links
                    ->where('links.workspace_id', $workspace->id)
                    ->select(['links.id', 'links.slug', 'links.title'])])
                ->where(fn ($qrCodes) => $qrCodes
                    ->whereRaw('title LIKE ? ESCAPE ?', [$pattern, $escape])
                    ->orWhereRaw('code LIKE ? ESCAPE ?', [$pattern, $escape])
                    ->orWhereHas('link', fn ($links) => $links
                        ->where('links.workspace_id', $workspace->id)
                        ->where(function ($links) use ($pattern, $escape) {
                            $links->whereRaw('title LIKE ? ESCAPE ?', [$pattern, $escape])
                                ->orWhereRaw('slug LIKE ? ESCAPE ?', [$pattern, $escape]);
                        })))
                ->orderBy('title')
                ->orderBy('id')
                ->limit(5)
                ->get(['id', 'title', 'code', 'link_id'])
                ->map(function ($qr): array {
                    $slug = $qr->link?->slug;

                    return [
                        'id' => (int) $qr->id,
                        'type' => 'qr_code',
                        'title' => $qr->title ?: ($qr->link?->title ?: 'Untitled QR campaign'),
                        'description' => $slug ? 'QR code for /'.$slug : 'QR campaign',
                        'href' => route('qr-codes.index', [], false).'#qr-campaign-'.$qr->id,
                    ];
                })
                ->all(),
            ...$workspace->customDomains()
                ->when(! $canManageIntegrations, fn ($domains) => $domains->where('status', 'active'))
                ->whereRaw('hostname LIKE ? ESCAPE ?', [$pattern, $escape])
                ->orderBy('hostname')
                ->limit(5)
                ->get(['id', 'hostname'])
                ->map(fn ($domain): array => [
                    'id' => (int) $domain->id,
                    'type' => 'custom_domain',
                    'title' => $domain->hostname,
                    'description' => 'Custom domain',
                    'href' => route('custom-domains.index', [], false).'#custom-domain-'.$domain->id,
                ])
                ->all(),
            ...$workspace->linkOverlays()
                ->whereRaw('name LIKE ? ESCAPE ?', [$pattern, $escape])
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name'])
                ->map(fn ($overlay): array => [
                    'id' => (int) $overlay->id,
                    'type' => 'overlay',
                    'title' => $overlay->name,
                    'description' => 'CTA overlay',
                    'href' => route('overlays.index', [], false).'#overlay-'.$overlay->id,
                ])
                ->all(),
            ...$workspace->trackingPixels()
                ->where(fn ($pixels) => $pixels->whereRaw('name LIKE ? ESCAPE ?', [$pattern, $escape])
                    ->orWhereRaw('provider LIKE ? ESCAPE ?', [$pattern, $escape]))
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name', 'provider'])
                ->map(fn ($pixel): array => [
                    'id' => (int) $pixel->id,
                    'type' => 'tracking_pixel',
                    'title' => $pixel->name,
                    'description' => ucfirst($pixel->provider).' pixel',
                    'href' => route('tracking-pixels.index', [], false).'#tracking-pixel-'.$pixel->id,
                ])
                ->all(),
            ...$workspace->webhooks()
                ->whereRaw('name LIKE ? ESCAPE ?', [$pattern, $escape])
                ->orderBy('name')
                ->limit(5)
                ->get(['id', 'name'])
                ->map(fn ($webhook): array => [
                    'id' => (int) $webhook->id,
                    'type' => 'webhook',
                    'title' => $webhook->name,
                    'description' => 'Webhook',
                    'href' => route('webhooks.index', [], false).'#webhook-'.$webhook->id,
                ])
                ->all(),
        ];

        return response()->json(['results' => $results]);
    }
}
