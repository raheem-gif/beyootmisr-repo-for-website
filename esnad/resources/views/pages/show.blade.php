@php
    $op = config('esnad.operator');
    $missing = app()->getLocale() === 'ar' ? 'غير محدد بعد' : 'not set yet';
    $tokens = [];
    foreach ($op as $k => $v) {
        $tokens['[['.$k.']]'] = $v ? e($v) : '<mark class="bg-warning-bg px-1 rounded-xs">'.$missing.'</mark>';
    }
    $body = strtr(\Illuminate\Support\Str::sanitizeHtml($page->body()), $tokens);
@endphp
<x-layouts.app :title="$page->title()">
    <article class="container-page py-12 md:py-16">
        <nav aria-label="breadcrumb" class="text-[14px] text-ink-2 mb-6">
            <a href="{{ route('home') }}" class="hover:underline">{{ __('site.home_link') }}</a>
            <x-glyph name="chevron" :flip="true" :size="14" class="inline mx-1" />
            <span aria-current="page">{{ $page->title() }}</span>
        </nav>
        <h1 class="text-3xl md:text-4xl font-semibold">{{ $page->title() }}</h1>
        <p class="mt-3 text-ink-2 text-[14px] tabular">
            {{ __('site.last_updated') }} <time datetime="{{ $page->updated_at->toDateString() }}">{{ $page->updated_at->locale(app()->getLocale() === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB')->translatedFormat('j F Y') }}</time>
            @if ($page->version) · {{ __('site.version') }} {{ $page->version }} @endif
        </p>
        <div class="prose-legal mt-8">{!! $body !!}</div>
    </article>
</x-layouts.app>
