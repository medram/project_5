<!DOCTYPE html>
@php
    $platformSettings = app(\App\Services\Settings\PlatformSettings::class)->resolved();
    $platformScheme = $platformSettings['themes']['default_scheme'] ?? 'system';
    $platformAccent = $platformSettings['themes']['accent'] ?? 'blue';
    $platformRadius = $platformSettings['themes']['radius'] ?? 'md';
    $platformVerification = $platformSettings['brand-verification'] ?? [];
    $platformSeo = $platformSettings['seo'] ?? [];
    $platformFaviconId = $platformSettings['general']['favicon_id'] ?? null;
    $localeDirection = request()->attributes->get('locale_model')?->direction ?? 'ltr';
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ $localeDirection }}" data-platform-scheme="{{ $platformScheme }}" data-platform-accent="{{ $platformAccent }}" data-platform-radius="{{ $platformRadius }}" @class(['dark' => ($appearance ?? 'system') == 'dark' || (($appearance ?? 'system') === 'system' && $platformScheme === 'dark')])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        @if (is_numeric($platformFaviconId) && (int) $platformFaviconId > 0)
            <link rel="icon" href="{{ route('platform.media', ['media' => (int) $platformFaviconId]) }}">
        @else
            <link rel="icon" href="/favicon.ico" sizes="any">
            <link rel="icon" href="/favicon.svg" type="image/svg+xml">
            <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        @endif
        @if (is_string($platformSeo['default_description'] ?? null) && $platformSeo['default_description'] !== '')
            <meta name="description" content="{{ $platformSeo['default_description'] }}">
        @endif
        @php
            $routeName = request()->route()?->getName();
            $routeSeo = match ($routeName) {
                'home' => [
                    'title' => $platformSeo['home_title'] ?? null,
                    'description' => $platformSeo['home_description'] ?? null,
                ],
                'pricing' => [
                    'title' => $platformSeo['pricing_title'] ?? null,
                    'description' => $platformSeo['pricing_description'] ?? null,
                ],
                default => ['title' => null, 'description' => null],
            };
            $socialTitle = $routeSeo['title'] ?: ($platformSeo['default_title'] ?? config('app.name'));
            $socialDescription = $routeSeo['description'] ?: ($platformSeo['default_description'] ?? null);
        @endphp
        <meta property="og:title" content="{{ $socialTitle }}">
        @if (is_string($socialDescription) && $socialDescription !== '')
            <meta property="og:description" content="{{ $socialDescription }}">
            <meta name="twitter:description" content="{{ $socialDescription }}">
        @endif
        <meta name="twitter:card" content="{{ is_numeric($platformSeo['og_media_id'] ?? null) ? 'summary_large_image' : 'summary' }}">
        @if (is_numeric($platformSeo['og_media_id'] ?? null) && (int) $platformSeo['og_media_id'] > 0)
            <meta property="og:image" content="{{ route('platform.media', ['media' => (int) $platformSeo['og_media_id']]) }}">
            <meta name="twitter:image" content="{{ route('platform.media', ['media' => (int) $platformSeo['og_media_id']]) }}">
        @endif
        @foreach (['google' => 'google-site-verification', 'bing' => 'msvalidate.01', 'pinterest' => 'p:domain_verify'] as $verificationKey => $verificationName)
            @if (is_string($platformVerification[$verificationKey.'_token'] ?? null) && $platformVerification[$verificationKey.'_token'] !== '')
                <meta name="{{ $verificationName }}" content="{{ $platformVerification[$verificationKey.'_token'] }}">
            @endif
        @endforeach

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
