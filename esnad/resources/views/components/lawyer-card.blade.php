@props(['lawyer'])
{{-- Result card (section 6.3). Nothing on the card is sponsored. Only what Article 71 allows on a sign. --}}
<article {{ $attributes->merge(['class' => 'card p-4 flex gap-4']) }}>
    <div class="size-16 md:size-20 rounded-lg bg-n-200 shrink-0 grid place-items-center text-ink-2 overflow-hidden">
        @if (! empty($lawyer['photo']))<img src="{{ $lawyer['photo'] }}" alt="" class="size-full object-cover">@else<x-glyph name="user" :size="28" />@endif
    </div>
    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-lg font-semibold">{{ $lawyer['name'] }}</h3>
            <span class="badge badge-verified"><x-glyph name="shield" :size="14" />{{ __('components.verified') }}</span>
            <span class="badge">{{ $lawyer['tier'] }}</span>
        </div>
        <p class="text-ink-2 text-[15px] mt-1">{{ implode('، ', $lawyer['areas']) }}</p>
        <p class="text-[14px] text-ink-2 mt-1 flex items-center gap-1"><x-glyph name="pin" :size="16" />{{ $lawyer['place'] }}</p>
        <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-[14px]">
            <span><span class="text-ink-2">{{ __('components.next_slot') }}</span> <span class="tabular font-medium">{{ $lawyer['next_slot'] }}</span></span>
            <span><span class="text-ink-2">{{ __('components.fee_from') }}</span> <span class="tabular font-medium">{{ \App\Support\Money::format($lawyer['fee_from']) }}</span></span>
        </div>
    </div>
</article>
