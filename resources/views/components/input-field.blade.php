@props(['label', 'name', 'value' => '', 'type' => 'text'])

<div class="mb-5">
    <label for="{{ $name }}" class="mb-1.5 block font-display font-semibold">{{ $label }}</label>

    {{-- Password fields are never re-filled after a failed submit --}}
    <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
        @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
        @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
        {{ $attributes->merge(['class' => 'focus-ring h-14 w-full rounded-xl border-2 border-line bg-canvas px-4 text-lg focus:border-brand focus:bg-surface aria-[invalid=true]:border-danger']) }}>

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 font-semibold text-danger">{{ $message }}</p>
    @enderror
</div>
