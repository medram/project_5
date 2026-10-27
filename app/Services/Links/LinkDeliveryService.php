<?php

namespace App\Services\Links;

use App\Models\Link;
use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class LinkDeliveryService
{
    public function __construct(
        private readonly LinkAccessUnlocks $unlocks,
        private readonly AnalyticsTracker $analytics,
        private readonly LinkRoutingResolver $routing,
        private readonly FirstPartyDestinationGuard $destinations,
    ) {}

    public function assertAvailable(Link $link): void
    {
        abort_unless($link->isAvailableAt(now()), 404);
        abort_if(
            $link->max_clicks !== null && $link->click_count >= $link->max_clicks,
            404,
        );
    }

    public function complete(Request $request, Link $link): string
    {
        $delivery = DB::transaction(function () use ($request, $link): array {
            $lockedLink = Link::query()->lockForUpdate()->findOrFail($link->getKey());

            abort_unless(
                (int) $lockedLink->workspace_id === (int) $link->workspace_id
                && $lockedLink->isAvailableAt(now()),
                404,
            );

            if ($lockedLink->requiresAccessCode()) {
                abort_unless($this->unlocks->allows($request, $lockedLink), 404);
            }

            $destination = $this->destination($request, $lockedLink);
            $now = now();
            $eligible = DB::table('links')
                ->where('id', $lockedLink->getKey())
                ->where('workspace_id', $lockedLink->workspace_id)
                ->where('revision_version', $lockedLink->revision_version)
                ->where('access_code_version', $lockedLink->access_code_version)
                ->where('is_active', true)
                ->whereNull('archived_at')
                ->where('approval_status', 'approved')
                ->where(function ($query) use ($now): void {
                    $query->whereNull('available_from')->orWhere('available_from', '<=', $now);
                })
                ->where(function ($query) use ($now): void {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', $now);
                })
                ->where(function ($query): void {
                    $query->whereNull('max_clicks')->orWhereColumn('click_count', '<', 'max_clicks');
                });

            abort_unless($eligible->increment('click_count') === 1, 404);
            $lockedLink->clicks()->create(['occurred_at' => now('UTC')]);

            return ['link' => $lockedLink, 'destination' => $destination];
        }, 5);

        try {
            $this->analytics->record($request, 'link.clicked', $delivery['link']->user, [
                'link' => $delivery['link'],
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return $delivery['destination'];
    }

    private function destination(Request $request, Link $link): string
    {
        $routingTarget = $this->routing->target($request, $link);
        $usesInternalPageRoute = false;

        if ($routingTarget !== null) {
            $destination = $routingTarget;
        } elseif ($link->delivery_mode === 'link_page') {
            $page = $link->linkPage;
            abort_unless(
                $page !== null
                && $page->is_published
                && (int) $page->workspace_id === (int) $link->workspace_id,
                404,
            );

            $destination = rtrim((string) config('app.url'), '/')
                .route('link-pages.public', ['slug' => $page->slug], false);
            $usesInternalPageRoute = true;
        } else {
            $destination = $link->destination_url;
        }

        abort_unless(
            is_string($destination)
            && $destination !== ''
            && (
                $usesInternalPageRoute
                || (
                    in_array(strtolower((string) parse_url($destination, PHP_URL_SCHEME)), ['http', 'https'], true)
                    && is_string(parse_url($destination, PHP_URL_HOST))
                    && ! $this->destinations->rejects($destination)
                )
            ),
            404,
        );

        $parameters = [];
        foreach ([$link->utm_parameters, $link->custom_parameters] as $set) {
            foreach (is_array($set) ? $set : [] as $key => $value) {
                if (is_string($key) && is_string($value) && $value !== '') {
                    $parameters[$key] = $value;
                }
            }
        }

        if ($parameters === []) {
            return $destination;
        }

        $fragmentPosition = strpos($destination, '#');
        $fragment = $fragmentPosition === false ? '' : substr($destination, $fragmentPosition);
        $withoutFragment = $fragmentPosition === false ? $destination : substr($destination, 0, $fragmentPosition);
        $queryPosition = strpos($withoutFragment, '?');
        $base = $queryPosition === false ? $withoutFragment : substr($withoutFragment, 0, $queryPosition);
        $existingQuery = $queryPosition === false ? '' : substr($withoutFragment, $queryPosition + 1);
        $retainedQueryParts = $existingQuery === ''
            ? []
            : array_values(array_filter(
                explode('&', $existingQuery),
                static function (string $part) use ($parameters): bool {
                    $key = explode('=', $part, 2)[0];

                    return ! array_key_exists(urldecode($key), $parameters);
                },
            ));
        $query = http_build_query($parameters);
        if ($retainedQueryParts !== []) {
            $retainedQuery = implode('&', $retainedQueryParts);
            $separator = str_ends_with($retainedQuery, '&') ? '' : '&';
            $query = $retainedQuery.$separator.$query;
        }

        return $base.'?'.$query.$fragment;
    }
}
