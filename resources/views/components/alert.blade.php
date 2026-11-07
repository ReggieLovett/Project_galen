@props(['type' => 'success'])

@php
    $styles = [
        'success' => 'bg-ok-soft text-ok',
        'error' => 'bg-danger-soft text-danger',
    ][$type];
@endphp

<div role="status" {{ $attributes->merge(['class' => "mb-5 rounded-xl px-4 py-3 font-semibold {$styles}"]) }}>
    {{ $slot }}
</div>
