<!DOCTYPE html>
@php
    $platformSettings = app(\App\Services\Settings\PlatformSettings::class)->resolved();
    $platformScheme = $platformSettings['themes']['default_scheme'] ?? 'system';
    $platformAccent = $platformSettings['themes']['accent'] ?? 'blue';
    $platformRadius = $platformSettings['themes']['radius'] ?? 'md';
    $platformVerification = $platformSettings['brand-verification'] ?? [];
    $platformSeo = $platformSettings['seo'] ?? [];
    $platformFaviconId = $platformSettings['general']['favicon_id'] ?? null;
@endphp
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-platform-scheme="{{ $platformScheme }}" data-platform-accent="{{ $platformAccent }}" data-platform-radius="{{ $platformRadius }}" @class(['dark' => ($appearance ?? 'system') == 'dark' || (($appearance ?? 'system') === 'system' && $platformScheme === 'dark')])>
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
        @foreach (['google' => 'google-site-verification', 'bing' => 'msvalidate.01', 'pinterest' => 'p:domain_verify'] as $verificationKey => $verificationName)
            @if (is_string($platformVerification[$verificationKey.'_token'] ?? null) && $platformVerification[$verificationKey.'_token'] !== '')
                <meta name="{{ $verificationName }}" content="{{ $platformVerification[$verificationKey.'_token'] }}">
            @endif
        @endforeach

        @fonts

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
