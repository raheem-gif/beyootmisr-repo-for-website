<x-layouts.app :title="__('auth.sign_in_title')" :noindex="true">
    <section class="container-page py-12 md:py-20">
        <div class="max-w-md mx-auto">
            <h1 class="text-3xl font-semibold">{{ __('auth.sign_in_title') }}</h1>
            <p class="text-ink-2 mt-2">{{ __('auth.sign_in_sub') }}</p>
            <form method="post" action="{{ route('auth.send') }}" class="mt-8 space-y-5" novalidate>
                @csrf
                <x-field name="phone" type="tel" :label="__('auth.phone_label')" :hint="__('auth.phone_hint')"
                    dir="ltr" inputmode="tel" autocomplete="tel-national" placeholder="01X XXXX XXXX" required x-data x-western-digits
                    class="text-start tabular text-lg" />
                <x-turnstile />
                <button type="submit" class="btn btn-primary w-full">{{ __('auth.send_code') }}</button>
            </form>
            <p class="mt-6 text-[14px] text-ink-2">
                <a class="link" href="{{ route('page', 'terms') }}">{{ __('site.legal_pages.terms') }}</a> ·
                <a class="link" href="{{ route('page', 'privacy') }}">{{ __('site.legal_pages.privacy') }}</a>
            </p>
        </div>
    </section>
</x-layouts.app>
