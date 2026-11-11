<x-layouts.kiosk title="Order {{ $order->order_number }}">
<div
    x-data="receipt({ seconds: {{ config('kiosk.receipt_timeout') }}, home: @js(route('welcome')), printDelay: {{ config('kiosk.receipt_print_delay') }} })"
    class="mx-auto grid min-h-dvh max-w-6xl items-center gap-10 px-5 py-8 sm:px-8 lg:grid-cols-[1fr_auto] lg:gap-16 short:py-5"
>
    <section class="no-print">
        <span class="flex size-16 items-center justify-center rounded-full bg-brand text-white" aria-hidden="true">
            <x-icon name="check" :size="34" :stroke="3" />
        </span>
        <h1 class="mt-6 text-5xl font-extrabold tracking-tight text-balance sm:text-6xl"><span class="sr-only">Order placed. </span>Take your slip to the cashier</h1>
        <p class="mt-5 max-w-xl text-xl leading-relaxed text-pretty text-ink-muted">
            Show the barcode or tell the cashier your order number. You pay there, and the team brings your items to the counter.
        </p>

        <div class="mt-8 rounded-2xl border-2 border-line bg-surface p-6">
            <p class="font-display text-lg font-semibold text-ink-muted">Your order number</p>
            <p class="font-display text-7xl font-extrabold tracking-tight tabular-nums">{{ $order->queueNumber() }}</p>
        </div>

        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
            {{-- Prints on its own when the fill reaches the end; a tap prints straight away.
                 aria-disabled (not disabled) while printing, so keyboard focus stays on it. --}}
            <button
                type="button"
                x-on:click="print()"
                x-bind:aria-disabled="printState === 'printing' || printState === 'returning' || leaving"
                class="btn btn-quiet relative isolate overflow-hidden aria-disabled:pointer-events-none aria-disabled:opacity-50 sm:flex-1"
                style="--print-delay: {{ config('kiosk.receipt_print_delay') }}s"
            >
                <span
                    x-show="printState === 'waiting'"
                    x-transition:leave="transition-opacity duration-150 ease-out"
                    x-transition:leave-end="opacity-0"
                    class="print-countdown absolute inset-0 -z-10 bg-brand-soft"
                    aria-hidden="true"
                ></span>
                <x-icon name="printer" />
                <span x-text="{ waiting: 'Print now', printing: 'Printing…', returning: 'Printing…', printed: 'Print again' }[printState]">Print now</span>
                <span x-show="printState === 'waiting'" class="font-sans text-base font-semibold text-ink-muted tabular-nums" aria-hidden="true" x-text="`${printIn}s`">{{ config('kiosk.receipt_print_delay') }}s</span>
            </button>
            {{-- Tapped before the slip is out, Done prints it first, then leaves. --}}
            <button type="button" x-on:click="done()" x-bind:aria-disabled="leaving" class="btn btn-primary aria-disabled:pointer-events-none sm:flex-[1.4]">
                <span x-text="leaving && printState !== 'printed' ? 'Printing your slip…' : 'Done'">Done</span><x-icon name="arrow-right" />
            </button>
        </div>

        <p class="mt-6 flex items-center gap-3 text-lg text-ink-muted" aria-live="off">
            <svg class="-rotate-90" width="32" height="32" viewBox="0 0 36 36" aria-hidden="true">
                <circle cx="18" cy="18" r="15" fill="none" stroke="var(--color-line)" stroke-width="4" />
                <circle
                    cx="18" cy="18" r="15" fill="none" stroke="var(--color-brand)" stroke-width="4" stroke-linecap="round"
                    stroke-dasharray="94.25"
                    x-bind:stroke-dashoffset="94.25 * (1 - remaining / seconds)"
                    class="transition-[stroke-dashoffset] duration-300 ease-linear"
                />
            </svg>
            <span>This screen resets for the next customer in <strong class="text-ink tabular-nums" x-text="remaining">{{ config('kiosk.receipt_timeout') }}</strong> seconds.</span>
        </p>
    </section>

    {{-- The kiosk's printer and the slip. A demo: printing pulls the slip up into the
         on-screen printer and out of view (receipt in app.js, .slip in app.css), as the
         real printer would take it. Nothing goes to a real printer. --}}
    <div x-ref="printer" class="mx-auto w-full max-w-sm scroll-mt-4">
        <p class="sr-only" aria-live="polite" x-text="{ printing: 'Printing your order slip.', printed: 'Your order slip is printed. Take it from the kiosk\'s printer.' }[printState] ?? ''"></p>

        <div class="no-print relative z-10 -mx-3 flex h-5 items-center gap-3 rounded-full bg-ink px-4 shadow-[0_8px_16px_-8px_rgb(23_48_45/0.6)]" aria-hidden="true">
            <span class="h-1 flex-1 rounded-full bg-black/70"></span>
            <span class="printer-light size-2 rounded-full" x-bind:data-print="printState"></span>
        </div>

        <div class="slip-feed relative -mt-2.5">
            {{-- Under the slip, uncovered as the printer pulls it in: where the slip went
                 and what to do next. The countdown lives on the Print button only. --}}
            <div
                x-show="printState !== 'waiting'"
                x-cloak
                class="no-print absolute inset-0 flex flex-col items-center justify-center gap-3 rounded-b-2xl border-2 border-t-0 border-dashed border-line-strong px-8 text-center"
                aria-hidden="true"
            >
                <span
                    class="flex size-14 items-center justify-center rounded-full transition-colors duration-200"
                    x-bind:class="printState === 'printed' ? 'bg-ok-soft text-ok' : 'bg-sunken text-ink-muted'"
                >
                    <x-icon name="check" :size="28" :stroke="3" x-show="printState === 'printed'" />
                    <x-icon name="printer" :size="28" x-show="printState !== 'printed'" />
                </span>
                <p class="font-display text-xl font-bold" x-text="printState === 'printed' ? 'Slip printed' : 'Printing your slip…'">Printing your slip…</p>
                <p class="max-w-64 text-lg leading-snug text-ink-muted">Take it from the kiosk's printer and show it at the cashier.</p>
            </div>

            <article
                x-ref="slip"
                data-print="waiting"
                x-bind:data-print="printState"
                x-on:animationend="printed($event)"
                class="slip receipt-paper w-full rounded-b-2xl bg-surface p-7 pt-9 short:p-5 short:pt-7 font-mono text-[15px] text-ink shadow-[0_2px_4px_rgb(23_48_45/0.06),0_24px_48px_-16px_rgb(23_48_45/0.28)]"
                aria-label="Order slip"
            >
                <header class="text-center">
                    <p class="font-display text-xl font-bold">{{ config('kiosk.name') }}</p>
                    <p class="mt-1 text-ink-muted">Order slip · pay at counter</p>
                    <p class="text-ink-muted">{{ $order->created_at->format('D j M Y, g:i A') }}</p>
                </header>

                <div class="my-5 border-t-2 border-dashed border-line short:my-3"></div>

                <div class="text-center">
                    <p class="text-ink-muted">ORDER</p>
                    <p class="font-display text-5xl font-extrabold tabular-nums short:text-4xl">{{ $order->queueNumber() }}</p>
                    <div class="mt-4 flex justify-center short:mt-2 [&_svg]:h-auto [&_svg]:max-w-full" role="img" aria-label="Barcode for order {{ $order->order_number }}">
                        {{-- Unescaped on purpose: the barcode library draws this SVG from our own order number. --}}
                        {!! $barcode !!}
                    </div>
                    <p class="mt-2 text-lg tracking-[0.2em]">{{ $order->order_number }}</p>
                </div>

                <div class="my-5 border-t-2 border-dashed border-line short:my-3"></div>

                <p class="flex justify-between gap-4">
                    <span class="text-ink-muted">Customer</span>
                    <span class="text-right">
                        @if ($order->user)
                            {{ $order->user->name }} · ••{{ substr($order->user->member_number, -4) }}
                        @else
                            Guest
                        @endif
                    </span>
                </p>

                <ul class="mt-4 space-y-3 short:mt-3 short:space-y-2">
                    @foreach ($order->items as $item)
                        <li>
                            <div class="flex justify-between gap-4">
                                <span>{{ $item->summary() }}</span>
                                <span class="tabular-nums"><x-money :amount="$item->line_total" /></span>
                            </div>
                            <p class="text-ink-muted">{{ $item->medicine?->packDescription($item->packaging) ?? $item->packaging->label() }} · {{ $item->strength }} · <x-money :amount="$item->unit_price" /> each</p>
                        </li>
                    @endforeach
                </ul>

                <div class="my-5 border-t-2 border-dashed border-line short:my-3"></div>

                <p class="flex items-baseline justify-between text-lg font-bold">
                    <span>TOTAL ({{ $order->item_count }} {{ Str::plural('item', $order->item_count) }})</span>
                    <span class="text-2xl tabular-nums"><x-money :amount="$order->total" /></span>
                </p>
                <p class="mt-1 text-ink-muted">Status: {{ $order->status->label() }}</p>

                @if ($order->hasPrescriptionItems())
                    <p class="mt-4 rounded-xl bg-rx-soft p-3 text-rx">Contains prescription items. The pharmacist will check them before handover.</p>
                @endif

                <p class="mt-5 text-center text-ink-muted short:mt-3">Thank you. Please keep this slip until you have paid.</p>
            </article>
        </div>
    </div>
</div>
</x-layouts.kiosk>
