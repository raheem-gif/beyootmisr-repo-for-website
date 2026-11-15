@props(['title' => null, 'description' => null, 'noindex' => false, 'bare' => false])
@php
    $locale = app()->getLocale();
    $other = $locale === 'ar' ? 'en' : 'ar';
    $brand = __('auth.brand');
    $pageTitle = $title ? $title.' | '.$brand : $brand;
    $route = request()->route();
    $altUrl = fn ($l) => $route && $route->getName() && str_starts_with(request()->path(), $locale)
        ? url($l.substr(request()->path(), 2))
        : url($l);
@endphp
<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $locale === 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $pageTitle }}</title>
    @if ($description)<meta name="description" content="{{ $description }}">@endif
    @if ($noindex)<meta name="robots" content="noindex, nofollow">@endif
    <link rel="canonical" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="{{ $locale }}" href="{{ url()->current() }}">
    <link rel="alternate" hreflang="{{ $other }}" href="{{ $altUrl($other) }}">
    <meta name="theme-color" content="#ffffff">
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="{{ asset('brand/icon-192.png') }}" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('brand/icon-180.png') }}">
    <meta property="og:site_name" content="{{ $brand }}">
    <meta property="og:title" content="{{ $pageTitle }}">
    @if ($description)<meta property="og:description" content="{{ $description }}">@endif
    <meta property="og:locale" content="{{ $locale === 'ar' ? 'ar_EG' : 'en_GB' }}">
    <link rel="preload" href="{{ Vite::asset('resources/fonts/ibm-plex-sans-arabic-arabic-400-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ Vite::asset('resources/fonts/ibm-plex-sans-arabic-arabic-600-normal.woff2') }}" as="font" type="font/woff2" crossorigin>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-dvh flex flex-col bg-canvas text-ink antialiased">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:top-2 focus:start-2 focus:z-50 btn btn-outline">{{ __('site.skip_to_content') }}</a>

    @unless ($bare)
        @include('partials.header', ['other' => $other, 'altUrl' => $altUrl])
    @endunless

    <main id="main" class="flex-1 relative isolate" tabindex="-1">
        {{ $slot }}
    </main>

    @unless ($bare)
        @include('partials.footer')
        {{-- Floating request pill on phones (section 8). --}}
        <div class="md:hidden fixed inset-x-0 bottom-0 z-30 pointer-events-none pb-[max(16px,env(safe-area-inset-bottom))] px-4 flex justify-center">
            <a href="{{ route('request.start') }}" class="btn btn-request btn-lg pointer-events-auto shadow-float">{{ __('site.nav.request_now') }}</a>
        </div>
    @endunless
</body>
</html>
