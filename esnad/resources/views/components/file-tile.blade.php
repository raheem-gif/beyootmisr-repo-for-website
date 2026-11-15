@props(['name', 'size', 'type' => 'PDF'])
<div {{ $attributes->merge(['class' => 'flex items-center gap-3 p-3 rounded-lg border border-n-400']) }}>
    <span class="grid place-items-center size-10 rounded-md bg-n-100 text-ink-2"><x-glyph name="file" /></span>
    <div class="min-w-0 flex-1">
        <p class="font-medium truncate"><bdi>{{ $name }}</bdi></p>
        <p class="text-[13px] text-ink-2 tabular">{{ $type }} · {{ $size }}</p>
    </div>
    <x-glyph name="lock" :size="18" class="text-ink-2" />
</div>
