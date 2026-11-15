@props(['name', 'label', 'hint' => null, 'type' => 'text', 'value' => null, 'dir' => null])
@php($id = $attributes->get('id', 'f-'.$name))
@php($error = $errors->first($name))
<div>
    <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name, $value) }}"
        @if ($dir) dir="{{ $dir }}" @endif
        @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
        {{ $attributes->except('id')->merge(['class' => 'input']) }}>
    @if ($error)
        <p id="{{ $id }}-error" class="field-error">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="field-hint">{{ $hint }}</p>
    @endif
</div>
