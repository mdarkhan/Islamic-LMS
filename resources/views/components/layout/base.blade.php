@props([
    'title' => null,
    'description' => null,   // meta description + OG description
    'canonical' => null,     // canonical + OG url
    'ogImage' => null,       // absolute image URL for OG
    'noindex' => false,      // protected areas set this true
])

@php $fullTitle = $title ? $title.' — মাসউদ আলিমী' : 'মাসউদ আলিমী'; @endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $fullTitle }}</title>

    @if ($description)<meta name="description" content="{{ $description }}">@endif
    @if ($canonical)<link rel="canonical" href="{{ $canonical }}">@endif
    @if ($noindex)<meta name="robots" content="noindex, nofollow">@endif

    {{-- Open Graph — lets shared links render a title/description card. --}}
    <meta property="og:site_name" content="মাসউদ আলিমী">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $title ?: 'মাসউদ আলিমী' }}">
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    @if ($canonical)<meta property="og:url" content="{{ $canonical }}">@endif
    @if ($ogImage)<meta property="og:image" content="{{ $ogImage }}">@endif

    {{-- Warms up the connection to Google Drive ahead of a lesson's embedded audio
         player (resources/views/student/courses/show.blade.php) — the DNS lookup +
         TLS handshake is a meaningful chunk of that iframe's slow-feeling first load,
         and this is cheap even on pages with no Drive embed (the browser just closes
         an idle connection after a few seconds). --}}
    <link rel="preconnect" href="https://drive.google.com">
    <link rel="preconnect" href="https://docs.google.com">

    {{-- Pre-paint theme to avoid a flash of the wrong palette. --}}
    <script>
        (function () {
            var t = localStorage.getItem('theme')
                || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (t === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>

    {{-- Kalpurush is base64-inlined into app.css itself (see vite.config.js), not a
         self-hosted font emitted via laravel-vite-plugin's fonts feature, so there is
         no separate Vite::fonts() call needed here — plain @vite() is enough. --}}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
    {{ $slot }}
</body>
</html>
