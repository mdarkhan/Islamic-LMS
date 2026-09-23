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

    {{-- Pre-paint theme to avoid a flash of the wrong palette. --}}
    <script>
        (function () {
            var t = localStorage.getItem('theme')
                || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            if (t === 'dark') document.documentElement.classList.add('dark');
        })();
    </script>

    {{-- @vite() alone never emits the self-hosted @font-face rules or their preload
         links — Vite::fonts() is a separate call that reads fonts-manifest.json and
         renders both. Without it the CSS's `--font-sans` name resolves to nothing the
         browser has ever heard of and silently falls through to the next stack entry. --}}
    {{ \Illuminate\Support\Facades\Vite::fonts() }}
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen antialiased">
    {{ $slot }}
</body>
</html>
