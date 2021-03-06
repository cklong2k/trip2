@php

$title = $title ?? '';
$name = $name ?? '';
$value = $value ?? '';

@endphp

<div class="FormPassword {{ $isclasses }} {{ $errors->first($name) ? 'FormPassword--error' : ''}}">

    @if ($title)

        <label for="{{ $name }}" class="FormPassword__label">{{ $title }}</label>
    
    @endif

    <input
        class="FormPassword__input"
        id="{{ $name }}"
        name="{{ $name }}"
        type="password"
        value="{{ $value }}"
        dusk="{{ slug($title) }}"
    >

</div>