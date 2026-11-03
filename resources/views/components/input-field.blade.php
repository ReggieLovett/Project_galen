@props(['label', 'name', 'value' => '', 'type' => 'text', 'keyboard' => false])

<div class="mb-5">
    <label for="{{ $name }}" class="mb-1.5 block font-display font-semibold">{{ $label }}</label>

    <div class="flex gap-2">
        {{-- Password fields are never re-filled after a failed submit. With `keyboard`, a touch opens the on-screen keyboard instead of the device's own. --}}
        <input type="{{ $type }}" id="{{ $name }}" name="{{ $name }}"
            @if ($type !== 'password') value="{{ old($name, $value) }}" @endif
            @if ($keyboard) inputmode="none" x-on:pointerdown="$event.pointerType === 'touch' && $dispatch('keyboard-show', @js($name))" @endif
            @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror
            {{ $attributes->merge(['class' => 'focus-ring h-14 w-full min-w-0 flex-1 rounded-xl border-2 border-line bg-canvas px-4 text-lg focus:border-brand focus:bg-surface aria-[invalid=true]:border-danger']) }}>

        @if ($keyboard)
            <button type="button" x-on:click="$dispatch('keyboard-toggle', @js($name))" class="btn-icon" aria-label="On-screen keyboard"><x-icon name="keyboard" /></button>
        @endif
    </div>

    @error($name)
        <p id="{{ $name }}-error" class="mt-1.5 font-semibold text-danger">{{ $message }}</p>
    @enderror
</div>
