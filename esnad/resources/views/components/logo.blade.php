@props(['height' => 32])
{{-- The logo is never mirrored. --}}
<picture>
    <source srcset="{{ asset('brand/esnad-logo-480.webp') }} 1x, {{ asset('brand/esnad-logo-960.webp') }} 2x" type="image/webp">
    <img src="{{ asset('brand/esnad-logo-480.png') }}" {{ $attributes }} alt="{{ __('auth.brand') }}" width="{{ (int) round($height * 1320 / 262) }}" height="{{ $height }}" style="height: {{ $height }}px; width: auto">
</picture>
