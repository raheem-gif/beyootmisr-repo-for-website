@props(['lines', 'total'])
{{-- Each charge on its own line, then the total (Consumer Protection Law, Article 7). Amounts in piastres. --}}
<dl {{ $attributes->merge(['class' => 'text-[15px]']) }}>
    @foreach ($lines as $line)
        <div class="flex justify-between gap-4 py-2">
            <dt class="text-ink-2">{{ $line['label'] }}</dt>
            <dd class="tabular">{{ \App\Support\Money::format($line['amount']) }}</dd>
        </div>
    @endforeach
    <div class="flex justify-between gap-4 pt-3 mt-1 border-t border-n-400 font-semibold text-lg">
        <dt>{{ __('components.total') }}</dt>
        <dd class="tabular">{{ \App\Support\Money::format($total) }}</dd>
    </div>
</dl>
