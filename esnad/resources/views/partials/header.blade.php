<header class="sticky top-0 z-40 pt-3 px-3" x-data="dialog">
    <div class="glass-nav max-w-[1200px] mx-auto flex items-center gap-4 h-16 ps-5 pe-3">
        <a href="{{ route('home') }}" class="shrink-0" aria-label="{{ __('site.home_link') }}"><x-logo :height="28" /></a>

        <nav class="hidden lg:flex items-center gap-1 ms-4" aria-label="{{ __('site.menu') }}">
            <a href="{{ route('home') }}#areas" class="btn btn-ghost btn-sm font-medium">{{ __('site.nav.practice_areas') }}</a>
            <a href="{{ route('search') }}" class="btn btn-ghost btn-sm font-medium">{{ __('site.nav.packages') }}</a>
            <a href="{{ route('search') }}" class="btn btn-ghost btn-sm font-medium">{{ __('site.nav.tools') }}</a>
            <a href="{{ route('search') }}" class="btn btn-ghost btn-sm font-medium">{{ __('site.nav.ask') }}</a>
            <a href="{{ route('home') }}#lawyers" class="btn btn-ghost btn-sm font-medium">{{ __('site.nav.for_lawyers') }}</a>
        </nav>

        <div class="ms-auto flex items-center gap-2">
            <a href="{{ $altUrl($other) }}" hreflang="{{ $other }}" lang="{{ $other }}" class="btn btn-ghost btn-sm" aria-label="{{ __('site.language_switch_label') }}">
                <x-glyph name="globe" :size="18" /><span class="hidden sm:inline">{{ __('site.language_switch') }}</span>
            </a>
            @auth
                <a href="{{ route('account') }}" class="btn btn-ghost btn-sm hidden sm:inline-flex"><x-glyph name="user" :size="18" />{{ __('site.nav.account') }}</a>
            @else
                <a href="{{ route('auth.sign-in') }}" class="btn btn-ghost btn-sm hidden sm:inline-flex">{{ __('site.nav.sign_in') }}</a>
            @endauth
            <a href="{{ route('request.start') }}" class="btn btn-request btn-sm hidden md:inline-flex">{{ __('site.nav.request_now') }}</a>
            <button type="button" class="btn btn-ghost btn-sm lg:hidden" x-on:click="show" aria-haspopup="dialog" x-bind:aria-expanded="open" aria-controls="mobile-menu">
                <x-glyph name="menu" /><span class="sr-only">{{ __('site.menu') }}</span>
            </button>
        </div>
    </div>

    {{-- Mobile menu --}}
    <div id="mobile-menu" x-cloak x-show="open" class="fixed inset-0 z-50 lg:hidden" role="dialog" aria-modal="true" aria-label="{{ __('site.menu') }}" x-on:keydown.escape.window="hide">
        <div class="absolute inset-0 bg-black/20 backdrop-blur-sm" x-on:click="hide"></div>
        <div x-ref="panel" class="absolute inset-y-0 end-0 w-[min(320px,85vw)] glass-strong !rounded-none p-4 flex flex-col gap-1 shadow-float"
             x-show="open" x-transition:enter="transition duration-300 ease-[var(--ease-enter)]" x-transition:enter-start="opacity-0 translate-x-4" x-transition:enter-end="opacity-100 translate-x-0">
            <div class="flex items-center justify-between mb-4">
                <x-logo :height="24" />
                <button type="button" class="btn btn-ghost btn-sm" x-on:click="hide"><x-glyph name="x" /><span class="sr-only">{{ __('site.close') }}</span></button>
            </div>
            <a href="{{ route('home') }}#areas" class="btn btn-ghost justify-start" x-on:click="hide">{{ __('site.nav.practice_areas') }}</a>
            <a href="{{ route('search') }}" class="btn btn-ghost justify-start">{{ __('site.nav.packages') }}</a>
            <a href="{{ route('search') }}" class="btn btn-ghost justify-start">{{ __('site.nav.tools') }}</a>
            <a href="{{ route('search') }}" class="btn btn-ghost justify-start">{{ __('site.nav.ask') }}</a>
            <a href="{{ route('home') }}#lawyers" class="btn btn-ghost justify-start" x-on:click="hide">{{ __('site.nav.for_lawyers') }}</a>
            <hr class="my-2 border-n-300">
            @auth
                <a href="{{ route('account') }}" class="btn btn-ghost justify-start"><x-glyph name="user" :size="18" />{{ __('site.nav.account') }}</a>
            @else
                <a href="{{ route('auth.sign-in') }}" class="btn btn-ghost justify-start">{{ __('site.nav.sign_in') }}</a>
            @endauth
            <a href="{{ route('request.start') }}" class="btn btn-request mt-auto">{{ __('site.nav.request_now') }}</a>
        </div>
    </div>
</header>
