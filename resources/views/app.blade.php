@inject('staticMedia', 'App\Services\StaticMediaService')
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#102A5C">

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: #f7f8fc;
            }
        </style>

        <link rel="icon" href="{{ $staticMedia->url('favicon.png') }}" type="image/png" sizes="128x128">
        <link rel="apple-touch-icon" href="{{ $staticMedia->url('apple-touch-icon.png') }}">
        <link rel="manifest" href="{{ route('site.manifest') }}">

        @fonts

        @viteReactRefresh
        @vite(['resources/css/app.css', 'resources/js/app.tsx', "resources/js/pages/{$page['component']}.tsx"])
        <x-inertia::head>
            @foreach ($page['props']['head'] ?? [] as $headElement)
                {!! $headElement !!}
            @endforeach
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
