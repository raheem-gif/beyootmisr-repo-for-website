<x-layouts.app :title="__('site.not_open.title')" :noindex="true">
    <section class="container-page py-16 md:py-24 max-w-2xl">
        <x-empty-state icon="clock" :title="__('site.not_open.title')" :text="__('site.not_open.'.$feature)" :action="__('site.not_open.back_home')" :href="route('home')" />
        <p class="mt-6 text-ink-2 text-center">{{ __('site.not_open.next') }} <a class="link" href="{{ route('page', 'legal-aid') }}">{{ __('site.not_open.aid_link') }}</a></p>
    </section>
</x-layouts.app>
