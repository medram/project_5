<?php

namespace App\Http\Controllers;

use App\Enums\MediaPurpose;
use App\Models\ContentMedia;
use App\Models\CustomPage;
use App\Services\Content\CustomPageHtmlSanitizer;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicCustomPageController extends Controller
{
    public function __invoke(string $slug, CustomPageHtmlSanitizer $sanitizer, Request $request): Response
    {
        $page = CustomPage::query()
            ->published()
            ->where('slug', $slug)
            ->firstOrFail();

        $image = ContentMedia::query()->whereKey($page->og_media_id)
            ->where('purpose', MediaPurpose::PlatformAsset)
            ->whereNull('workspace_id')->whereNull('expires_at')
            ->where('visibility', 'private')
            ->whereIn('mime_type', ['image/jpeg', 'image/png', 'image/webp'])
            ->whereIn('scan_state', ['clean', 'not_required'])
            ->first();

        $request->attributes->set('custom_page_seo', [
            'title' => $page->seo_title,
            'description' => $page->seo_description,
            'image' => $image ? route('platform.media', ['media' => $image->id]) : null,
        ]);

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
