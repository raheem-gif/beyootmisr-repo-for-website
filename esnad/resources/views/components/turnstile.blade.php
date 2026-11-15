@if (\App\Services\Turnstile::enabled())
    <div class="cf-turnstile" data-sitekey="{{ config('esnad.turnstile.site_key') }}" data-language="{{ app()->getLocale() }}" data-size="flexible"></div>
    @push('head')
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer nonce="{{ app('csp-nonce') }}"></script>
    @endpush
@endif
