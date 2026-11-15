@props(['name', 'size' => 20, 'flip' => false])
{{-- Stroke icons drawn on a 24px grid. Directional icons take :flip="true" so they mirror in right to left. --}}
@php
$paths = [
    'search' => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
    'pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
    'phone' => '<path d="M5 4h3l2 5-2.5 1.5a11 11 0 0 0 6 6L15 14l5 2v3a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2z"/>',
    'video' => '<rect x="3" y="6" width="13" height="12" rx="2"/><path d="m16 10 5-3v10l-5-3z"/>',
    'chat' => '<path d="M4 5h16v11H8l-4 4z"/>',
    'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
    'chevron' => '<path d="m9 6 6 6-6 6"/>',
    'chevron-down' => '<path d="m6 9 6 6 6-6"/>',
    'check' => '<path d="m5 12.5 4.5 4.5L19 7.5"/>',
    'shield' => '<path d="M12 3 5 6v5c0 4.5 3 8.3 7 10 4-1.7 7-5.5 7-10V6z"/><path d="m9 12 2 2 4-4"/>',
    'x' => '<path d="M6 6l12 12M18 6 6 18"/>',
    'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
    'globe' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z"/>',
    'alert' => '<path d="M12 3 2 20h20z"/><path d="M12 10v4M12 17.5v.01"/>',
    'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 7.5v.01"/>',
    'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    'file' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/>',
    'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
    'building' => '<path d="M4 21V5l8-3 8 3v16"/><path d="M9 21v-4h6v4M8 8h.01M12 8h.01M16 8h.01M8 12h.01M12 12h.01M16 12h.01"/>',
    'lock' => '<rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V8a4 4 0 0 1 8 0v3"/>',
    'upload' => '<path d="M12 16V4M7 9l5-5 5 5M4 20h16"/>',
    'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
    'card' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/>',
    'sos' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 16.5v.01"/>',
];
@endphp
<svg {{ $attributes->merge(['class' => $flip ? 'flip-rtl shrink-0' : 'shrink-0']) }} width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">{!! $paths[$name] ?? '' !!}</svg>
