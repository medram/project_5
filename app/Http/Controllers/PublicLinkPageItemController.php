<?php

namespace App\Http\Controllers;

use App\Models\LinkPage;
use App\Models\LinkPageItem;
use App\Services\Analytics\AnalyticsTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Throwable;

class PublicLinkPageItemController extends Controller
{
    public function __construct(private readonly AnalyticsTracker $analytics) {}

    public function __invoke(
        Request $request,
        string $slug,
        LinkPageItem $linkPageItem,
    ): RedirectResponse {
        $linkPage = LinkPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('user:id')
            ->firstOrFail();

        abort_unless(
            $linkPageItem->link_page_id === $linkPage->id && $linkPageItem->is_active,
            404,
        );

        try {
            $this->analytics->record($request, 'link_page.clicked', $linkPage->user, [
                'linkPage' => $linkPage,
                'linkPageItem' => $linkPageItem,
            ]);
        } catch (Throwable $exception) {
            report($exception);
        }

        return redirect()->away($linkPageItem->destination_url);
    }
}
