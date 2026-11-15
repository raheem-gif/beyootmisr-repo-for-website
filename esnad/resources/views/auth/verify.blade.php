@php($len = config('esnad.otp.length'))
<x-layouts.app :title="__('auth.verify_title')" :noindex="true">
    <section class="container-page py-12 md:py-20">
        <div class="max-w-md mx-auto">
            <h1 class="text-3xl font-semibold">{{ __('auth.verify_title') }}</h1>
            <p class="text-ink-2 mt-2">{{ __($channel === 'sms' ? 'auth.verify_sub_sms' : 'auth.verify_sub_whatsapp') }} <bdi dir="ltr" class="tabular font-medium text-ink">{{ \App\Support\Phone::display($phone) }}</bdi></p>

            <form method="post" action="{{ route('auth.verify.submit') }}" class="mt-8" x-data="otpInput({{ $len }})" novalidate>
                @csrf
                <fieldset>
                    <legend class="field-label">{{ __('auth.code_label') }}</legend>
                    {{-- Digits always run left to right, even in Arabic. --}}
                    <div dir="ltr" class="flex gap-2 justify-between" x-on:paste="onPaste">
                        @for ($i = 0; $i < $len; $i++)
                            <input x-ref="d{{ $i }}" type="text" inputmode="numeric" maxlength="{{ $len }}"
                                @if ($i === 0) autocomplete="one-time-code" autofocus @else autocomplete="off" @endif
                                aria-label="{{ __('auth.digit_label', ['n' => $i + 1, 'total' => $len]) }}"
                                class="input text-center text-2xl font-semibold tabular !px-0 w-full max-w-14 h-14"
                                @error('code') aria-invalid="true" @enderror
                                x-on:input="onInput({{ $i }}, $event)" x-on:keydown="onKeydown({{ $i }}, $event)">
                        @endfor
                    </div>
                    <input type="hidden" name="code" x-bind:value="value">
                    @error('code')<p class="field-error" role="alert">{{ $message }}</p>@enderror
                </fieldset>
                <button type="submit" class="btn btn-primary w-full mt-6">{{ __('auth.verify_button') }}</button>
            </form>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3 text-[15px]" x-data="countdown({{ $resendIn }}, '{{ e(__('auth.resend_in')) }}')">
                <form method="post" action="{{ route('auth.resend') }}">
                    @csrf
                    <button type="submit" class="link disabled:no-underline disabled:text-ink-2" x-bind:disabled="left > 0">{{ __('auth.resend') }}</button>
                </form>
                <p class="text-ink-2 tabular" x-show="left > 0" x-text="message"></p>
                <a href="{{ route('auth.sign-in') }}" class="link">{{ __('auth.change_number') }}</a>
            </div>
        </div>
    </section>
</x-layouts.app>
