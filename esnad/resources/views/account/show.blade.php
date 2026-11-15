@php($dateLocale = app()->getLocale() === 'ar' ? 'ar-EG-u-nu-latn' : 'en-GB')
<x-layouts.app :title="__('account.title')" :noindex="true">
    <section class="container-page py-10 md:py-14">
        <x-flash />
        <div class="flex flex-wrap items-center justify-between gap-4">
            <h1 class="text-3xl font-semibold">{{ __('account.title') }}</h1>
            <form method="post" action="{{ route('auth.sign-out') }}">@csrf<button class="btn btn-outline btn-sm">{{ __('site.nav.sign_out') }}</button></form>
        </div>

        <div class="mt-8 grid gap-8 lg:grid-cols-12">
            <div class="lg:col-span-5 space-y-8">
                <section class="card p-6" aria-labelledby="profile-title">
                    <h2 id="profile-title" class="text-xl font-semibold">{{ __('account.profile') }}</h2>
                    <p class="mt-2 text-ink-2 text-[15px]">{{ __('account.phone') }} <bdi dir="ltr" class="tabular text-ink">{{ \App\Support\Phone::display($user->phone) }}</bdi></p>
                    <form method="post" action="{{ route('account.update') }}" class="mt-6 space-y-5">
                        @csrf @method('put')
                        <x-field name="name" :label="__('auth.name_label')" :value="$user->name_ar ?? $user->name_en" required />
                        <div>
                            <label for="address_form" class="field-label">{{ __('auth.address_form_label') }}</label>
                            <select id="address_form" name="address_form" class="input">
                                <option value="">{{ __('auth.address_form_none') }}</option>
                                <option value="m" @selected($user->address_form === 'm')>{{ __('auth.address_form_m') }}</option>
                                <option value="f" @selected($user->address_form === 'f')>{{ __('auth.address_form_f') }}</option>
                            </select>
                        </div>
                        <div>
                            <label for="governorate_id" class="field-label">{{ __('auth.governorate_label') }}</label>
                            <select id="governorate_id" name="governorate_id" class="input">
                                @foreach ($governorates as $g)
                                    <option value="{{ $g->id }}" @selected($user->governorate_id == $g->id)>{{ $g->name() }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="locale" class="field-label">{{ __('site.language_switch_label') }}</label>
                            <select id="locale" name="locale" class="input">
                                <option value="ar" @selected($user->locale === 'ar')>العربية</option>
                                <option value="en" @selected($user->locale === 'en')>English</option>
                            </select>
                        </div>
                        <button class="btn btn-primary">{{ __('account.save') }}</button>
                    </form>
                </section>

                <x-empty-state icon="calendar" :title="__('account.empty_bookings_title')" :text="__('account.empty_bookings_text')" />
            </div>

            <section class="lg:col-span-7 card p-6" aria-labelledby="consents-title">
                <h2 id="consents-title" class="text-xl font-semibold">{{ __('consent.title') }}</h2>
                <p class="text-ink-2 mt-1 text-[15px]">{{ __('consent.intro') }}</p>
                <ul class="mt-6 divide-y divide-n-300">
                    @foreach ($current as $purpose => $consent)
                        <li class="py-4 flex flex-wrap gap-4 items-start justify-between">
                            <div class="max-w-md">
                                <p class="font-medium">{{ __("consent.purposes.$purpose.label") }}</p>
                                <p class="text-[14px] mt-1 {{ $consent ? 'text-success' : 'text-ink-2' }}">
                                    @if ($consent)
                                        {{ __('consent.given_on', ['date' => $consent->given_at->locale($dateLocale)->translatedFormat('j F Y')]) }}
                                    @else
                                        {{ __('consent.not_given') }}
                                    @endif
                                </p>
                            </div>
                            @if ($purpose === 'service')
                                <p class="text-[14px] text-ink-2 max-w-56">{{ __('consent.withdraw_service_note') }}</p>
                            @elseif ($consent)
                                <form method="post" action="{{ route('consents.withdraw', $purpose) }}">@csrf @method('delete')<button class="btn btn-outline btn-sm">{{ __('consent.withdraw') }}</button></form>
                            @else
                                <form method="post" action="{{ route('consents.give', $purpose) }}">@csrf<button class="btn btn-outline btn-sm">{{ __('consent.give') }}</button></form>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <h3 class="mt-8 font-semibold">{{ __('consent.history') }}</h3>
                <ol class="mt-3 space-y-2 text-[14px] text-ink-2">
                    @foreach ($history as $c)
                        <li class="flex flex-wrap gap-x-3">
                            <span class="text-ink">{{ __("consent.purposes.{$c->purpose}.label") }}</span>
                            <span class="tabular">{{ __('consent.given_on', ['date' => $c->given_at->locale($dateLocale)->translatedFormat('j F Y, H:i')]) }}</span>
                            @if ($c->withdrawn_at)<span class="tabular">{{ __('consent.withdrawn_on', ['date' => $c->withdrawn_at->locale($dateLocale)->translatedFormat('j F Y, H:i')]) }}</span>@endif
                            <span>{{ __("consent.method.{$c->method}") }}</span>
                            <span class="tabular">{{ __('consent.version', ['version' => $c->version]) }}</span>
                        </li>
                    @endforeach
                </ol>
            </section>
        </div>
    </section>
</x-layouts.app>
