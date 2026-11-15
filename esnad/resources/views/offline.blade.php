<x-layouts.app :title="__('site.pwa.offline_title')" :noindex="true" :bare="true">
    <section class="container-page py-24 max-w-md text-center">
        <x-logo :height="28" class="mx-auto" />
        <x-empty-state class="mt-10" icon="globe" :title="__('site.pwa.offline_title')" :text="__('site.pwa.offline_text')" :action="__('site.pwa.retry')" href="/" />
    </section>
</x-layouts.app>
