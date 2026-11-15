@php
    $op = config('esnad.operator');
    $rows = array_filter([
        'operator' => $op['company_name'],
        'cr' => $op['cr_no'],
        'tax_card' => $op['tax_card_no'],
        'vat' => $op['vat_no'],
        'address' => $op['office_address'],
    ]);
    $legal = ['terms', 'client-terms', 'lawyer-terms', 'privacy', 'cookies', 'refunds', 'reviews', 'community', 'fraud', 'dpo', 'legal-aid'];
@endphp
<footer class="glass-bar !border-b-0 border-t border-white/60 mt-24 pb-28 md:pb-0">
    <div class="container-page py-12 grid gap-10 md:grid-cols-12 text-[15px]">
        <div class="md:col-span-5 space-y-4">
            <x-logo :height="28" />
            <p class="text-ink-2 max-w-md">{{ __('site.footer.neutral') }}</p>
            <div class="alert alert-warning text-[14px] flex gap-3 items-start">
                <x-glyph name="alert" :size="18" class="mt-1 text-warning" />
                <p>{{ __('site.footer.fraud') }} <a class="link" href="{{ route('page', 'fraud') }}">{{ __('site.footer.fraud_link') }}</a></p>
            </div>
        </div>

        <nav class="md:col-span-3" aria-labelledby="footer-legal">
            <h2 id="footer-legal" class="font-semibold mb-3 text-base">{{ __('site.footer.legal') }}</h2>
            <ul class="space-y-2">
                @foreach ($legal as $slug)
                    <li><a class="text-ink-2 hover:text-ink hover:underline" href="{{ route('page', $slug) }}">{{ __("site.legal_pages.$slug") }}</a></li>
                @endforeach
            </ul>
        </nav>

        {{-- Operator identity (Anti-Cybercrime Law) --}}
        <div class="md:col-span-4">
            <dl class="space-y-2">
                @foreach ($rows as $key => $value)
                    <div><dt class="text-ink-2 text-[13px]">{{ __("site.footer.$key") }}</dt><dd><bdi>{{ $value }}</bdi></dd></div>
                @endforeach
                @if ($op['support_phone'])
                    <div><dt class="text-ink-2 text-[13px]">{{ __('site.footer.phone') }}</dt><dd><a dir="ltr" class="hover:underline" href="tel:{{ $op['support_phone'] }}">{{ $op['support_phone'] }}</a></dd></div>
                @endif
                @if ($op['support_whatsapp'])
                    <div><dt class="text-ink-2 text-[13px]">{{ __('site.footer.whatsapp') }}</dt><dd><a dir="ltr" class="hover:underline" href="https://wa.me/{{ preg_replace('/\D/', '', $op['support_whatsapp']) }}">{{ $op['support_whatsapp'] }}</a></dd></div>
                @endif
                @if ($op['support_email'])
                    <div><dt class="text-ink-2 text-[13px]">{{ __('site.footer.email') }}</dt><dd><a dir="ltr" class="hover:underline" href="mailto:{{ $op['support_email'] }}">{{ $op['support_email'] }}</a></dd></div>
                @endif
                @if ($op['dpo_name'] || $op['dpo_email'])
                    <div><dt class="text-ink-2 text-[13px]">{{ __('site.footer.dpo') }}</dt><dd><bdi>{{ $op['dpo_name'] }}</bdi> @if ($op['dpo_email'])<a dir="ltr" class="hover:underline" href="mailto:{{ $op['dpo_email'] }}">{{ $op['dpo_email'] }}</a>@endif</dd></div>
                @endif
                @if ($op['pdpc_licence_no'])
                    <div><dt class="text-ink-2 text-[13px]">{{ __('site.footer.pdpc') }}</dt><dd><bdi>{{ $op['pdpc_licence_no'] }}</bdi></dd></div>
                @endif
            </dl>
        </div>
    </div>
    <div class="border-t border-white/60">
        <div class="container-page py-4 text-[13px] text-ink-2 flex flex-wrap gap-x-6 gap-y-2 justify-between">
            <p>© {{ now()->year }} {{ $op['company_name'] ?: __('site.footer.company') }}. {{ __('site.footer.rights') }}.</p>
        </div>
    </div>
</footer>
