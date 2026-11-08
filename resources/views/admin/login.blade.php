<x-layouts.kiosk title="Staff login" noindex class="flex items-center justify-center px-5 py-10 [.keyboard-open_&]:items-start [.keyboard-open_&]:pb-[29rem]">
    <div class="w-full">
        <p class="mb-6 flex items-center justify-center gap-3">
            <x-logo-mark class="size-12 rounded-xl bg-brand text-white" />
            <span class="font-display text-xl font-bold">{{ config('kiosk.name') }}</span>
        </p>
        <x-auth-card heading="Staff login">
            @session('success')
                <x-alert>{{ $value }}</x-alert>
            @endsession

            <form action="{{ route('admin.login.store') }}" method="POST" x-data>
                @csrf

                <x-input-field label="Email" name="email" type="email" autocomplete="username" keyboard autofocus required />
                <x-input-field label="Password" name="password" type="password" autocomplete="current-password" keyboard required />

                <button type="submit" class="btn btn-primary mt-2 w-full">Log in</button>
            </form>
        </x-auth-card>

        <p class="mt-6 text-center">
            <a href="{{ route('welcome') }}" class="focus-ring rounded-lg px-2 py-1 font-semibold text-brand underline-offset-4 hover:underline">Back to the kiosk</a>
        </p>
    </div>

    {{-- A login screen left open goes back to "Touch to begin" on its own --}}
    <x-idle-timer leave="Back to the kiosk">
        The kiosk will go back to the start screen in <strong class="text-ink tabular-nums" x-text="`${remaining} seconds`"></strong>.
    </x-idle-timer>

    <x-on-screen-keyboard full />
</x-layouts.kiosk>
