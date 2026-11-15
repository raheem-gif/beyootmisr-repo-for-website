<x-layouts.app :title="__('site.home.title')" :description="__('site.home.meta_description')">
    @php
        $requestIcons = ['phone' => 'phone', 'video' => 'video', 'police' => 'shield', 'prosecution' => 'building', 'court' => 'building', 'visit' => 'pin'];
    @endphp

    <div class="orbs" aria-hidden="true">
        <div class="orb orb-blue" style="width:520px;height:520px;inset-block-start:-120px;inset-inline-end:-140px"></div>
        <div class="orb orb-deep" style="width:420px;height:420px;inset-block-start:260px;inset-inline-start:-160px"></div>
        <div class="orb orb-sky" style="width:560px;height:560px;inset-block-start:900px;inset-inline-end:10%"></div>
        <div class="orb orb-blue" style="width:460px;height:460px;inset-block-start:1500px;inset-inline-start:-120px"></div>
        <div class="orb orb-slate" style="width:520px;height:520px;inset-block-start:2300px;inset-inline-end:-160px"></div>
        <div class="orb orb-deep" style="width:480px;height:480px;inset-block-start:2900px;inset-inline-start:20%"></div>
    </div>

    {{-- Hero: one line with one accent phrase, the request sheet beside it. --}}
    <section class="container-page pt-10 pb-12 md:pt-20 md:pb-16 grid gap-10 lg:grid-cols-12 items-start">
        <div class="lg:col-span-7">
            <x-flash />
            <h1 class="text-[36px] leading-[1.2] md:text-[56px] md:leading-[1.15] font-semibold tracking-normal">
                {{ __('site.home.hero_line') }}
                <span class="text-accent">{{ __('site.home.hero_accent') }}</span>
            </h1>
            <p class="mt-5 text-lg md:text-xl text-ink-2 max-w-2xl">{{ __('site.home.hero_sub') }}</p>

            <div class="mt-8 flex flex-wrap gap-3">
                <a href="{{ route('request.start') }}" class="btn btn-request btn-lg">{{ __('site.nav.request_now') }}</a>
                <a href="{{ route('search') }}" class="btn btn-outline btn-lg">{{ __('site.nav.book') }}</a>
            </div>
        </div>

        <aside class="lg:col-span-5" aria-labelledby="request-sheet-title">
            <div class="glass-strong p-5 md:p-6">
                <h2 id="request-sheet-title" class="text-xl font-semibold">{{ __('site.home.request_sheet_title') }}</h2>
                <p class="text-ink-2 mt-1 text-[15px]">{{ __('site.home.request_sheet_sub') }}</p>
                <ul class="mt-5 grid grid-cols-2 gap-2">
                    @foreach (__('site.home.request_types') as $key => $label)
                        <li>
                            <a href="{{ route('request.start') }}" class="glass-tile flex-col gap-3 h-full p-3">
                                <span class="glow-chip"><x-glyph :name="$requestIcons[$key]" :size="20" /></span>
                                <span class="text-[15px] font-medium leading-snug">{{ $label }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-4 text-[14px] text-ink-2 flex gap-2"><x-glyph name="info" :size="18" class="mt-0.5" />{{ __('site.home.request_types_note') }}</p>
            </div>
        </aside>
    </section>

    {{-- Search band, directly under the hero. --}}
    <section class="container-page" aria-labelledby="search-title">
        <div class="glass p-5 md:p-6">
            <form action="{{ route('search') }}" method="get" class="grid gap-3 md:grid-cols-12 items-end" role="search">
                <div class="md:col-span-7">
                    <label id="search-title" for="q" class="field-label text-lg">{{ __('site.home.search_label') }}</label>
                    <div class="relative">
                        <x-glyph name="search" class="absolute top-1/2 -translate-y-1/2 start-4 text-ink-2 pointer-events-none" />
                        <input id="q" name="q" type="search" class="input ps-12" placeholder="{{ __('site.home.search_placeholder') }}" x-data x-western-digits autocomplete="off">
                    </div>
                </div>
                <div class="md:col-span-3">
                    <label for="gov" class="field-label">{{ __('site.home.governorate_label') }}</label>
                    <select id="gov" name="governorate" class="input">
                        <option value="">{{ __('site.home.all_governorates') }}</option>
                        @foreach ($governorates as $g)
                            <option value="{{ $g->slug }}">{{ $g->name() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button type="submit" class="btn btn-primary w-full">{{ __('site.home.search_button') }}</button>
                </div>
            </form>
        </div>
    </section>

    {{-- Practice areas as bento tiles --}}
    <section id="areas" class="container-page py-16 md:py-24" aria-labelledby="areas-title">
        <h2 id="areas-title" class="text-3xl md:text-4xl font-semibold">{{ __('site.home.areas_title') }}</h2>
        <p class="text-ink-2 mt-2 text-lg">{{ __('site.home.areas_sub') }}</p>
        <ul class="mt-8 grid gap-3 grid-cols-2 md:grid-cols-4">
            @foreach ($areas as $i => $key)
                <li class="{{ $i === 0 ? 'col-span-2 md:row-span-2' : '' }}">
                    <a href="{{ route('search', ['area' => $key]) }}" class="group card flex flex-col justify-between h-full p-4 md:p-5 {{ $i === 0 ? 'min-h-[180px] md:min-h-full !bg-gradient-to-br from-white/80 to-accent-soft/60' : 'min-h-[120px]' }}">
                        <span class="{{ $i === 0 ? 'text-2xl' : 'text-lg' }} font-semibold">{{ __("site.areas.$key.name") }}</span>
                        <span class="flex items-end justify-between gap-2 mt-3">
                            <span class="text-ink-2 text-[14px]">{{ __("site.areas.$key.hint") }}</span>
                            <x-glyph name="arrow" :flip="true" :size="18" class="text-ink-2 group-hover:text-ink" />
                        </span>
                    </a>
                </li>
            @endforeach
        </ul>
    </section>

    {{-- How it works --}}
    <section class="container-page" aria-labelledby="how-title">
        <div class="glass p-6 md:p-12">
            <h2 id="how-title" class="text-3xl md:text-4xl font-semibold">{{ __('site.home.how_title') }}</h2>
            <ol class="mt-10 grid gap-8 md:grid-cols-4">
                @foreach (__('site.home.how') as $n => $step)
                    <li>
                        <span class="glow-chip font-semibold tabular text-lg">{{ $n + 1 }}</span>
                        <h3 class="mt-4 text-xl font-semibold">{{ $step['title'] }}</h3>
                        <p class="mt-2 text-ink-2">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Platform numbers: shown only when there is a real number behind them. --}}
    @if ($numbers['verified_lawyers'] > 0)
        <section class="container-page py-16" aria-labelledby="numbers-title">
            <h2 id="numbers-title" class="sr-only">{{ __('site.home.numbers_title') }}</h2>
            <dl class="grid gap-6 sm:grid-cols-3">
                <div class="card p-6"><dd class="text-5xl font-semibold tabular">{{ $numbers['verified_lawyers'] }}</dd><dt class="text-ink-2 mt-2">{{ __('site.home.numbers.verified_lawyers') }}</dt></div>
                <div class="card p-6"><dd class="text-5xl font-semibold tabular">{{ $numbers['governorates'] }}</dd><dt class="text-ink-2 mt-2">{{ __('site.home.numbers.governorates') }}</dt></div>
                @if ($numbers['median_response'])
                    <div class="card p-6"><dd class="text-5xl font-semibold tabular">{{ $numbers['median_response'] }}</dd><dt class="text-ink-2 mt-2">{{ __('site.home.numbers.median_response') }}</dt></div>
                @endif
            </dl>
        </section>
    @endif

    {{-- Trust promises --}}
    <section class="container-page py-16 md:py-24" aria-labelledby="promises-title">
        <h2 id="promises-title" class="text-3xl md:text-4xl font-semibold">{{ __('site.home.promises_title') }}</h2>
        <ul class="mt-10 grid gap-4 md:grid-cols-2">
            @foreach (__('site.home.promises') as $promise)
                <li class="glass p-6 md:p-8">
                    <span class="glow-chip"><x-glyph name="check" :size="20" /></span>
                    <h3 class="mt-3 text-xl font-semibold">{{ $promise['title'] }}</h3>
                    <p class="mt-2 text-ink-2">{{ $promise['text'] }}</p>
                </li>
            @endforeach
        </ul>
        <p class="mt-6 text-ink-2">{{ __('site.home.aid_text') }} <a href="{{ route('page', 'legal-aid') }}" class="link">{{ __('site.home.aid_link') }}</a></p>
    </section>

    {{-- For lawyers --}}
    <section id="lawyers" class="container-page" aria-labelledby="lawyers-title">
        <div class="glass-dark p-8 md:p-14 grid gap-6 md:grid-cols-12 items-center">
            <div class="md:col-span-8 relative z-10">
                <h2 id="lawyers-title" class="text-3xl md:text-4xl font-semibold">{{ __('site.home.lawyers_title') }}</h2>
                <p class="mt-4 text-lg text-white/85 max-w-2xl">{{ __('site.home.lawyers_text') }}</p>
            </div>
            <div class="md:col-span-4 md:text-end relative z-10">
                <a href="{{ route('auth.sign-in') }}" class="btn btn-lg bg-white/90 text-ink hover:bg-white shadow-float">{{ __('site.home.lawyers_cta') }}</a>
            </div>
        </div>
    </section>

    {{-- Closing band with one action --}}
    <section class="container-page py-16 md:py-24 text-center">
        <h2 class="text-3xl md:text-4xl font-semibold">{{ __('site.home.closing_title') }}</h2>
        <a href="{{ route('search') }}" class="btn btn-primary btn-lg mt-8">{{ __('site.home.closing_cta') }}</a>
    </section>
</x-layouts.app>
