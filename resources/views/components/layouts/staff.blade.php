@props(['title', 'section' => 'Staff', 'subtitle' => null])

{{--
    Every signed-in staff page: the header with the page's own actions (the
    `actions` slot) and Log out, and the "Are you still there?" timer that
    logs staff out. The kiosk is a shared device, so no staff page may stay
    open after someone walks away.
--}}
<x-layouts.kiosk :title="$title" noindex>
    <header class="flex flex-wrap items-center gap-4 border-b-2 border-line bg-surface px-6 py-4">
        <a href="{{ route('admin.dashboard') }}" class="focus-ring shrink-0 rounded-xl" aria-label="Staff dashboard">
            <x-logo-mark class="size-12 rounded-xl bg-brand text-white" />
        </a>
        <div class="min-w-0 flex-1">
            <p class="font-display text-xl font-bold">{{ config('kiosk.name') }} · {{ $section }}</p>
            <p class="text-ink-muted">Signed in as {{ auth('admin')->user()->name }}{{ filled($subtitle) ? " · {$subtitle}" : '' }}</p>
        </div>
        <nav class="flex flex-wrap items-center gap-2" aria-label="Staff">
            {{ $actions ?? '' }}
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn btn-quiet"><x-icon name="log-out" />Log out</button>
            </form>
        </nav>
    </header>

    {{ $slot }}

    <x-idle-timer leave="Log out now">
        For security, you'll be logged out and the kiosk will go back to the start screen in <strong class="text-ink tabular-nums" x-text="`${remaining} seconds`"></strong>.
    </x-idle-timer>
</x-layouts.kiosk>
