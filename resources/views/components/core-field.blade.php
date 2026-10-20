@props(['name', 'label', 'value' => null, 'type' => 'text', 'help' => null])
@php
    $invalid = $errors->has($name);
    $describedBy = trim(($help ? $name.'-help ' : '').($invalid ? $name.'-error' : ''));
@endphp
<label for="{{ $name }}">
    {{ $label }}
    @if ($type === 'select')
        <select id="{{ $name }}" name="{{ $name }}" {{ $attributes }} aria-invalid="{{ $invalid ? 'true' : 'false' }}" @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>{{ $slot }}</select>
    @elseif ($type === 'textarea')
        <textarea id="{{ $name }}" name="{{ $name }}" {{ $attributes }} aria-invalid="{{ $invalid ? 'true' : 'false' }}" @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>{{ $value }}</textarea>
    @else
        <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" value="{{ $value }}" {{ $attributes }} aria-invalid="{{ $invalid ? 'true' : 'false' }}" @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif>
    @endif
    @if ($help)<span id="{{ $name }}-help" class="history-note">{{ $help }}</span>@endif
    @if ($invalid)<span id="{{ $name }}-error" class="identity-field-error">{{ $errors->first($name) }}</span>@endif
</label>
