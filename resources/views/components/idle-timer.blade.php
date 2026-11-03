@props(['leave' => 'Start over'])

{{--
    "Are you still there?" after the idle timeout. If nobody answers, the
    reset form signs everyone out and returns to "Touch to begin". The slot
    is the message; `remaining` holds the seconds left.
--}}
<div x-data="idleTimer({ timeout: {{ config('kiosk.idle_timeout') }}, countdown: {{ config('kiosk.idle_countdown') }} })">
    <form x-ref="reset" method="POST" action="{{ route('kiosk.reset') }}" class="hidden">@csrf</form>
    <div x-show="open" x-cloak x-trap.noscroll="open" class="fixed inset-0 z-[80] flex items-center justify-center p-5" role="alertdialog" aria-modal="true" aria-labelledby="idle-title" aria-describedby="idle-message">
        <div class="absolute inset-0 bg-ink/70"></div>
        <div class="relative w-full max-w-md rounded-2xl bg-surface p-8 text-center">
            <span class="mx-auto flex size-20 items-center justify-center rounded-full bg-sunken text-ink"><x-icon name="clock" :size="40" /></span>
            <h2 id="idle-title" class="mt-5 text-3xl font-bold">Are you still there?</h2>
            <p id="idle-message" class="mt-3 text-lg text-ink-muted">{{ $slot }}</p>
            <div class="mt-8 flex flex-col gap-3">
                <button type="button" x-on:click="stillHere()" class="btn btn-primary" autofocus>I'm still here</button>
                <button type="button" x-on:click="$refs.reset.submit()" class="btn btn-quiet">{{ $leave }}</button>
            </div>
        </div>
    </div>
</div>
