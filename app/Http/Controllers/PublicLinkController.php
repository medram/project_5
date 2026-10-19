<?php

namespace App\Http\Controllers;

use App\Models\Link;
use Illuminate\Http\RedirectResponse;

class PublicLinkController extends Controller
{
    public function __invoke(string $slug): RedirectResponse
    {
        $link = Link::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();

        $link->clicks()->create([
            'occurred_at' => now(),
        ]);

        return redirect()->away($link->destination_url, 302);
    }
}
