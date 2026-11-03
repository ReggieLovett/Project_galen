@props(['full' => false])

{{--
    Touch-screen keyboard. Inputs open it by dispatching keyboard-show or
    keyboard-toggle with their id. `full` adds Shift and a symbol row so
    staff can type an email address and password.
--}}
<div
    x-data="keyboard"
    x-on:keyboard-toggle.window="toggle($event.detail)"
    x-on:keyboard-show.window="show($event.detail)"
    x-effect="document.documentElement.classList.toggle('keyboard-open', open)"
    x-show="open"
    x-cloak
    x-transition:enter="transition duration-200 ease-out-quart"
    x-transition:enter-start="translate-y-full"
    class="fixed inset-x-0 bottom-0 z-[70] border-t-2 border-line-strong bg-sunken px-3 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
    role="group"
    aria-label="On-screen keyboard"
>
    <div class="mx-auto max-w-5xl space-y-2">
        @foreach (['1234567890', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm'] as $row)
            <div class="flex justify-center gap-2 {{ $loop->iteration === 3 ? 'px-[5%]' : '' }}">
                @if ($full && $loop->last)
                    <button type="button" x-on:mousedown.prevent x-on:click="press('shift')" x-bind:aria-pressed="shift" class="key max-w-36 flex-[1.6] text-base normal-case aria-pressed:bg-ink aria-pressed:text-white" tabindex="-1">Shift</button>
                @endif
                @foreach (str_split($row) as $key)
                    <button type="button" x-on:mousedown.prevent x-on:click="press('{{ $key }}')" @if ($full) x-bind:class="{ 'normal-case': ! shift }" @endif class="key" tabindex="-1">{{ $key }}</button>
                @endforeach
                @if ($loop->last)
                    <button type="button" x-on:mousedown.prevent x-on:click="press('backspace')" class="key max-w-36 flex-[1.6]" tabindex="-1" aria-label="Backspace"><x-icon name="delete" /></button>
                @endif
            </div>
        @endforeach
        @if ($full)
            <div class="flex justify-center gap-2">
                @foreach (str_split('@.-_!?#$%&*+') as $key)
                    <button type="button" x-on:mousedown.prevent x-on:click="press(@js($key))" class="key" tabindex="-1">{{ $key }}</button>
                @endforeach
            </div>
        @endif
        <div class="flex gap-2">
            <button type="button" x-on:mousedown.prevent x-on:click="press('clear')" class="key max-w-40 text-base normal-case" tabindex="-1">Clear</button>
            <button type="button" x-on:mousedown.prevent x-on:click="press('space')" class="key flex-[5] text-base normal-case" tabindex="-1">Space</button>
            <button type="button" x-on:mousedown.prevent x-on:click="press('enter')" class="key max-w-48 flex-[1.5] bg-brand! text-base text-white! normal-case shadow-[0_2px_0_var(--color-brand-hover)]!" tabindex="-1">Go</button>
        </div>
    </div>
</div>
