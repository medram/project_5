<?php

namespace App\Http\Controllers;

use App\Models\CustomPage;
use App\Services\Content\CustomPageHtmlSanitizer;
use Inertia\Inertia;
use Inertia\Response;

class PublicCustomPageController extends Controller
{
    public function __invoke(string $slug, CustomPageHtmlSanitizer $sanitizer): Response
    {
        $page = CustomPage::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('content/show', [
            'page' => [
                'slug' => $page->slug,
                'title' => $page->title,
                'body' => $sanitizer->sanitize($page->body),
                'state' => $page->state,
                'seoTitle' => $page->seo_title,
                'seoDescription' => $page->seo_description,
                'publishedAt' => $page->published_at?->toISOString(),
            ],
            'preview' => false,
        ]);
    }
}
