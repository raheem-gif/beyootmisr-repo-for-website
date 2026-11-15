@php
    $sample = ['name' => app()->getLocale() === 'ar' ? 'محامي تجريبي' : 'Sample Lawyer', 'tier' => __('components.tiers.appeal'), 'areas' => [__('site.areas.family.name'), __('site.areas.inheritance.name')], 'place' => app()->getLocale() === 'ar' ? 'القاهرة، مدينة نصر' : 'Cairo, Nasr City', 'next_slot' => app()->getLocale() === 'ar' ? 'بكرة 11:00' : 'Tomorrow 11:00', 'fee_from' => 50000];
@endphp
<x-layouts.app title="Design system" :noindex="true">
    <div class="container-page py-10 space-y-14">
        <div class="alert alert-warning">{{ __('components.sample') }}</div>

        <section class="space-y-4">
            <h2 class="text-2xl font-semibold">Colour</h2>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-3 text-[13px]">
                @foreach (['bg-accent' => 'accent #1F64A8', 'bg-ink' => 'ink #141413', 'bg-ink-2' => 'ink-2 #5F5E5A', 'bg-brand-blue' => 'logo blue', 'bg-brand-slate' => 'logo slate', 'bg-band' => 'band #FAF9F5', 'bg-error' => 'error', 'bg-success' => 'success', 'bg-warning' => 'warning', 'bg-n-100' => 'n-100', 'bg-n-400' => 'n-400', 'bg-n-700' => 'n-700'] as $cls => $label)
                    <div><div class="h-14 rounded-md border border-n-400 {{ $cls }}"></div><p class="mt-1">{{ $label }}</p></div>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-semibold">Type</h2>
            <p class="text-5xl font-semibold">{{ __('site.home.hero_line') }} <span class="text-accent">{{ __('site.home.hero_accent') }}</span></p>
            <p class="text-3xl font-semibold">{{ __('site.home.how_title') }}</p>
            <p class="text-xl">{{ __('site.home.hero_sub') }}</p>
            <p class="font-legal text-lg max-w-2xl">إسناد برنامج حجز محايد يربط العميل بمحامٍ مستقل مقيد في نقابة المحامين. المنصة لا تقدّم استشارات قانونية.</p>
            <p class="tabular text-2xl">{{ \App\Support\Money::format(35000) }} · {{ \App\Support\Money::format(123450) }} · 14:30</p>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-semibold">Buttons</h2>
            <div class="flex flex-wrap gap-3 items-center">
                <button class="btn btn-request">{{ __('site.nav.request_now') }}</button>
                <button class="btn btn-primary">{{ __('site.nav.book') }}</button>
                <button class="btn btn-outline">{{ __('auth.change_number') }}</button>
                <button class="btn btn-ghost">{{ __('site.close') }}</button>
                <button class="btn btn-danger">{{ __('components.modal_confirm') }}</button>
                <button class="btn btn-primary" disabled>{{ __('auth.send_code') }}</button>
                <button class="btn btn-outline btn-sm">{{ __('consent.withdraw') }}</button>
            </div>
        </section>

        <section class="grid gap-6 md:grid-cols-2">
            <div class="space-y-5">
                <h2 class="text-2xl font-semibold">Inputs</h2>
                <x-field name="d_phone" type="tel" :label="__('auth.phone_label')" :hint="__('auth.phone_hint')" dir="ltr" class="text-start tabular" placeholder="01X XXXX XXXX" x-data x-western-digits />
                <div>
                    <label class="field-label" for="d_err">{{ __('auth.name_label') }}</label>
                    <input id="d_err" class="input" aria-invalid="true" value="ا">
                    <p class="field-error">{{ __('validation.min.string', ['attribute' => __('validation.attributes.name'), 'min' => 3]) }}</p>
                </div>
                <div>
                    <label class="field-label" for="d_sel">{{ __('auth.governorate_label') }}</label>
                    <select id="d_sel" class="input"><option>{{ __('auth.choose') }}</option></select>
                </div>
                <div>
                    <label class="field-label" for="d_ta">{{ __('site.home.search_label') }}</label>
                    <textarea id="d_ta" class="input" maxlength="300"></textarea>
                </div>
            </div>
            <div class="space-y-5">
                <h2 class="text-2xl font-semibold">OTP</h2>
                <form x-data="otpInput(6, false)" x-on:submit.prevent>
                    <div dir="ltr" class="flex gap-2" x-on:paste="onPaste">
                        @for ($i = 0; $i < 6; $i++)
                            <input x-ref="d{{ $i }}" inputmode="numeric" maxlength="6" aria-label="{{ __('auth.digit_label', ['n' => $i + 1, 'total' => 6]) }}" class="input text-center text-2xl font-semibold tabular !px-0 w-full max-w-14 h-14" x-on:input="onInput({{ $i }}, $event)" x-on:keydown="onKeydown({{ $i }}, $event)">
                        @endfor
                    </div>
                </form>
                <h2 class="text-2xl font-semibold pt-4">Badges and alerts</h2>
                <div class="flex flex-wrap gap-2">
                    <span class="badge badge-verified"><x-glyph name="shield" :size="14" />{{ __('components.verified') }}</span>
                    <span class="badge">{{ __('components.tiers.cassation') }}</span>
                    <span class="badge badge-accent">{{ __('consent.required') }}</span>
                </div>
                <div class="alert alert-info">{{ __('site.general_information') }}</div>
                <div class="alert alert-error">{{ __('auth.code_invalid') }}</div>
            </div>
        </section>

        <section class="grid gap-6 md:grid-cols-2">
            <div class="space-y-4">
                <h2 class="text-2xl font-semibold">Lawyer card</h2>
                <x-lawyer-card :lawyer="$sample" />
                <div class="card p-4 flex gap-4">
                    <div class="skeleton size-20"></div>
                    <div class="flex-1 space-y-3"><div class="skeleton h-5 w-2/3"></div><div class="skeleton h-4 w-1/2"></div><div class="skeleton h-4 w-1/3"></div></div>
                </div>
                <x-file-tile name="عقد-إيجار.pdf" size="1.2 MB" />
            </div>
            <div class="space-y-4">
                <h2 class="text-2xl font-semibold">Price breakdown</h2>
                <div class="card p-5">
                    <x-price-breakdown :lines="[['label' => __('components.lawyer_fee'), 'amount' => 50000], ['label' => __('components.booking_fee'), 'amount' => 3000], ['label' => __('components.vat'), 'amount' => 420]]" :total="53420" />
                </div>
                <h2 class="text-2xl font-semibold pt-2">Status stepper</h2>
                <x-status-stepper :steps="__('components.dispatch_steps')" :current="2" />
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-semibold">Tabs</h2>
            @php($tabs = __('components.tabs'))
            <div x-data="tabs(0)">
                <div role="tablist" class="flex gap-1 border-b border-n-400 overflow-x-auto" x-on:keydown="key($event, {{ count($tabs) }})">
                    @foreach (array_values($tabs) as $i => $label)
                        <button type="button" role="tab" x-ref="tab{{ $i }}" id="tab-{{ $i }}" aria-controls="panel-{{ $i }}"
                            x-bind:aria-selected="active === {{ $i }}" x-bind:tabindex="active === {{ $i }} ? 0 : -1"
                            x-on:click="select({{ $i }})"
                            class="px-4 min-h-11 -mb-px border-b-2 font-medium whitespace-nowrap"
                            x-bind:class="active === {{ $i }} ? 'border-ink text-ink' : 'border-transparent text-ink-2'">{{ $label }}</button>
                    @endforeach
                </div>
                @foreach (array_values($tabs) as $i => $label)
                    <div role="tabpanel" id="panel-{{ $i }}" aria-labelledby="tab-{{ $i }}" x-show="active === {{ $i }}" class="py-4 text-ink-2">{{ $label }}</div>
                @endforeach
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-semibold">Modal, bottom sheet, toast</h2>
            <div class="flex flex-wrap gap-3">
                <div x-data="dialog">
                    <button class="btn btn-outline" x-on:click="show">{{ __('components.open_modal') }}</button>
                    <div x-cloak x-show="open" class="fixed inset-0 z-50 grid place-items-center p-4" role="dialog" aria-modal="true" aria-labelledby="m-title" x-on:keydown.escape.window="hide">
                        <div class="absolute inset-0 bg-black/40" x-on:click="hide"></div>
                        <div x-ref="panel" class="relative card shadow-float p-6 w-full max-w-md" x-show="open" x-transition.opacity.duration.200ms>
                            <h3 id="m-title" class="text-xl font-semibold">{{ __('components.modal_title') }}</h3>
                            <p class="mt-2 text-ink-2">{{ __('components.modal_text') }}</p>
                            <div class="mt-6 flex gap-3 justify-end">
                                <button class="btn btn-outline" x-on:click="hide">{{ __('components.modal_keep') }}</button>
                                <button class="btn btn-danger" x-on:click="hide">{{ __('components.modal_confirm') }}</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div x-data="dialog">
                    <button class="btn btn-outline" x-on:click="show">{{ __('components.open_sheet') }}</button>
                    <div x-cloak x-show="open" class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="s-title" x-on:keydown.escape.window="hide">
                        <div class="absolute inset-0 bg-black/30" x-on:click="hide"></div>
                        <div x-ref="panel" class="sheet absolute inset-x-0 bottom-0 p-5 pb-[max(20px,env(safe-area-inset-bottom))] max-w-xl mx-auto"
                             x-show="open" x-transition:enter="transition duration-[350ms] ease-[var(--ease-sheet)]" x-transition:enter-start="translate-y-full" x-transition:enter-end="translate-y-0">
                            <div class="mx-auto mb-4 h-1 w-10 rounded-full bg-n-500"></div>
                            <h3 id="s-title" class="text-xl font-semibold">{{ __('site.home.request_sheet_title') }}</h3>
                            <p class="text-ink-2 mt-1">{{ __('site.home.request_sheet_sub') }}</p>
                            <button class="btn btn-request w-full mt-5" x-on:click="hide">{{ __('site.nav.request_now') }}</button>
                        </div>
                    </div>
                </div>
                <button class="btn btn-outline" x-data x-on:click="$store.toast.push('{{ __('components.toast_text') }}', 'success')">{{ __('components.show_toast') }}</button>
            </div>
            <div x-data class="fixed bottom-24 md:bottom-6 inset-x-0 z-50 flex flex-col items-center gap-2 pointer-events-none" aria-live="polite">
                <template x-for="t in $store.toast.items" x-bind:key="t.id">
                    <div class="pointer-events-auto bg-ink text-white rounded-md px-4 py-3 shadow-float flex items-center gap-2"><span x-text="t.message"></span></div>
                </template>
            </div>
        </section>

        <section class="space-y-4">
            <h2 class="text-2xl font-semibold">Empty state</h2>
            <x-empty-state icon="calendar" :title="__('account.empty_bookings_title')" :text="__('account.empty_bookings_text')" :action="__('site.nav.book')" href="#" />
        </section>
    </div>
</x-layouts.app>
