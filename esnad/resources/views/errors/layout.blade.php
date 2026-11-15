@php
    $seg = request()->segment(1);
    app()->setLocale(in_array($seg, ['ar', 'en']) ? $seg : 'ar');
    \Illuminate\Support\Facades\URL::defaults(['locale' => app()->getLocale()]);
@endphp
<x-layouts.app :title="__('errors.'.$code.'_title')" :noindex="true" :bare="true">
    <section class="container-page py-20 md:py-28 max-w-lg text-center">
        <a href="{{ url(app()->getLocale()) }}"><x-logo :height="28" class="mx-auto" /></a>
        <p class="mt-12 text-ink-2 tabular font-medium">{{ $code }}</p>
        <h1 class="mt-2 text-3xl font-semibold">{{ __('errors.'.$code.'_title') }}</h1>
        <p class="mt-3 text-ink-2 text-lg">{{ __('errors.'.$code.'_text') }}</p>
        <a href="{{ url(app()->getLocale()) }}" class="btn btn-primary mt-8">{{ __('errors.back_home') }}</a>
    </section>
</x-layouts.app>
