<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="Discover more with ProDeals.lk, Sri Lanka's marketplace for everyday finds and better deals.">
        <meta name="theme-color" content="#102A5C">
        <link rel="canonical" href="{{ config('app.url') }}">
        <meta property="og:site_name" content="ProDeals.lk">
        <meta property="og:type" content="website">
        <meta property="og:title" content="ProDeals.lk — Better deals. Closer to home.">
        <meta property="og:description" content="Discover more with ProDeals.lk, Sri Lanka's marketplace for everyday finds and better deals.">
        <meta property="og:image" content="{{ rtrim(config('app.url'), '/') }}/prodeals-social-card.png">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="ProDeals.lk — Better deals. Closer to home.">
        <meta name="twitter:description" content="Discover more with ProDeals.lk, Sri Lanka's marketplace for everyday finds and better deals.">
        <meta name="twitter:image" content="{{ rtrim(config('app.url'), '/') }}/prodeals-social-card.png">

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
                background-color: #f7f8fc;
            }

            html.dark {
                background-color: #0b1731;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">
        <link rel="manifest" href="/site.webmanifest">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            <title>{{ config('app.name', 'ProDeals.lk') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
