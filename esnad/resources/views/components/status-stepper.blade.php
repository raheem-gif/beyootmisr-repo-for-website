@props(['steps', 'current' => 0])
{{-- Steps run in reading direction, so they mirror in right to left automatically. --}}
<ol {{ $attributes->merge(['class' => 'flex items-start']) }}>
    @foreach ($steps as $i => $label)
        @php($state = $i < $current ? 'done' : ($i === $current ? 'current' : 'todo'))
        <li class="flex-1 flex flex-col items-center text-center relative" @if ($state === 'current') aria-current="step" @endif>
            @if ($i > 0)
                <span class="absolute top-3.5 end-1/2 w-full h-0.5 {{ $i <= $current ? 'bg-ink' : 'bg-n-400' }}" aria-hidden="true"></span>
            @endif
            <span class="relative grid place-items-center size-7 rounded-full border-2 text-[13px] font-semibold tabular
                {{ $state === 'done' ? 'bg-ink border-ink text-white' : ($state === 'current' ? 'bg-white border-ink text-ink' : 'bg-white border-n-400 text-ink-2') }}">
                @if ($state === 'done')<x-glyph name="check" :size="14" />@else{{ $i + 1 }}@endif
            </span>
            <span class="mt-2 text-[13px] leading-tight {{ $state === 'todo' ? 'text-ink-2' : 'text-ink font-medium' }}">{{ $label }}</span>
        </li>
    @endforeach
</ol>
