<x-layouts.kiosk title="Staff login" noindex class="flex items-center justify-center px-5 py-10">
    <div class="w-full">
        <p class="mb-6 flex items-center justify-center gap-3">
            <x-logo-mark class="size-12 rounded-xl bg-ink text-white" />
            <span class="font-display text-xl font-bold">{{ config('kiosk.name') }}</span>
        </p>
        <x-auth-card heading="Staff login">
            @session('success')
                <x-alert>{{ $value }}</x-alert>
            @endsession

            <form action="{{ route('admin.login.store') }}" method="POST">
                @csrf

                <x-input-field label="Email" name="email" type="email" autocomplete="username" autofocus required />
                <x-input-field label="Password" name="password" type="password" autocomplete="current-password" required />

                <button type="submit" class="btn btn-primary mt-2 w-full">Log in</button>
            </form>
        </x-auth-card>

        <p class="mt-6 text-center">
            <a href="{{ route('welcome') }}" class="focus-ring rounded-lg px-2 py-1 font-semibold text-brand underline-offset-4 hover:underline">Back to the kiosk</a>
        </p>
    </div>
</x-layouts.kiosk>
