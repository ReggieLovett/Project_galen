<x-layouts.kiosk class="overflow-hidden bg-brand text-white">
    <form method="POST" action="{{ route('kiosk.start') }}" class="relative flex min-h-dvh flex-col">
        @csrf

        {{-- The whole screen is the button. --}}
        <button type="submit" class="focus-ring absolute inset-0 z-10 cursor-pointer" aria-label="Touch to begin"></button>

        {{-- A large white cross behind everything, like the sign over a drugstore door. --}}
        <svg class="pointer-events-none absolute top-1/2 -right-40 size-[44rem] -translate-y-1/2 text-white/[0.07]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
            <path d="M9 3h6v6h6v6h-6v6H9v-6H3V9h6z" />
        </svg>

        <header class="relative flex items-center justify-between px-8 pt-8 sm:px-12 sm:pt-10">
            {{-- Hidden staff entrance: double-tap the logo. One tap still begins. --}}
            <div x-data="staffDoor(@js(route('admin.login')))" x-on:click="tap()" class="relative z-20 flex cursor-pointer touch-manipulation items-center gap-4">
                <x-logo-mark class="size-14 rounded-xl bg-white text-brand shadow-lg shadow-brand-deep/30" />
                <span class="font-display text-2xl font-bold tracking-tight">{{ config('kiosk.name') }}</span>
            </div>
            <time x-data="clock" x-text="time" class="font-display text-2xl font-semibold tabular-nums"></time>
        </header>

        <main class="relative flex flex-1 flex-col items-center justify-center px-8 text-center">
            <div class="relative mb-14 flex size-44 items-center justify-center" aria-hidden="true">
                <span class="ripple absolute inset-0 rounded-full bg-white/35"></span>
                <span class="ripple ripple-delay absolute inset-0 rounded-full bg-white/35"></span>
                <span class="relative flex size-36 items-center justify-center rounded-full bg-white text-brand shadow-xl shadow-brand-deep/30">
                    <x-icon name="arrow-right" :size="56" :stroke="2.5" />
                </span>
            </div>

            <h1 class="text-6xl font-extrabold tracking-tight sm:text-8xl">Touch to begin</h1>
            <p class="mt-6 max-w-2xl text-xl leading-relaxed text-pretty text-white/90 sm:text-2xl">
                Find your medicines, buy them by the piece or by the box, refill a prescription you have filled with us before, and pay at the counter.
            </p>
        </main>

        {{-- How it works, in order, on a white band under the red --}}
        <footer class="relative border-t-8 border-brand-deep bg-surface px-8 py-8 text-ink sm:px-12 sm:py-10">
            <ol class="flex flex-col items-center justify-center gap-4 font-display text-xl font-semibold sm:flex-row sm:gap-0">
                @foreach ([['search', 'Search or browse'], ['basket', 'Add to your basket'], ['card', 'Pay at the counter']] as [$icon, $step])
                    <li class="flex items-center gap-3">
                        @unless ($loop->first)
                            <x-icon name="arrow-right" class="mx-6 hidden text-brand/50 sm:block" />
                        @endunless
                        <span class="flex size-12 items-center justify-center rounded-full bg-brand text-white"><x-icon :name="$icon" /></span>
                        {{ $step }}
                    </li>
                @endforeach
            </ol>
        </footer>
    </form>
</x-layouts.kiosk>
