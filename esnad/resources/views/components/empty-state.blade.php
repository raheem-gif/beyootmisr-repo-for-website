@props(['icon' => 'file', 'title', 'text', 'action' => null, 'href' => null])
{{-- Icon, title, one line, one action (after Vercel Geist). --}}
<div {{ $attributes->merge(['class' => 'flex flex-col items-center text-center px-6 py-12 border border-dashed border-n-500 rounded-lg']) }}>
    <span class="grid place-items-center size-12 rounded-full bg-n-100 text-ink-2 mb-4"><x-glyph :name="$icon" :size="22" /></span>
    <h3 class="text-lg font-semibold">{{ $title }}</h3>
    <p class="text-ink-2 mt-1 max-w-sm">{{ $text }}</p>
    @if ($action && $href)
        <a href="{{ $href }}" class="btn btn-outline btn-sm mt-5">{{ $action }}</a>
    @endif
</div>
