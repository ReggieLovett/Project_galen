<x-layouts.kiosk title="Order {{ $order->order_number }}">
<div
    x-data="receipt({ seconds: {{ config('kiosk.receipt_timeout') }}, home: @js(route('welcome')) })"
    class="mx-auto grid min-h-dvh max-w-6xl items-center gap-10 px-5 py-8 sm:px-8 lg:grid-cols-[1fr_auto] lg:gap-16"
>
    <section class="no-print order-2 lg:order-1">
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
            <button type="button" x-on:click="print()" class="btn btn-quiet sm:flex-1">
                <x-icon name="printer" />Print slip
            </button>
            <button type="button" x-on:click="done()" class="btn btn-primary sm:flex-[1.4]">
                Done<x-icon name="arrow-right" />
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

    {{-- The slip itself --}}
    <article class="receipt-paper order-1 mx-auto w-full max-w-sm rounded-2xl bg-surface p-7 font-mono text-[15px] text-ink shadow-[0_2px_4px_rgb(15_46_29/0.06),0_24px_48px_-16px_rgb(15_46_29/0.28)] lg:order-2" aria-label="Order slip">
        <header class="text-center">
            <p class="font-display text-xl font-bold">{{ config('kiosk.name') }}</p>
            <p class="mt-1 text-ink-muted">Order slip · pay at counter</p>
            <p class="text-ink-muted">{{ $order->created_at->format('D j M Y, g:i A') }}</p>
        </header>

        <div class="my-5 border-t-2 border-dashed border-line"></div>

        <div class="text-center">
            <p class="text-ink-muted">ORDER</p>
            <p class="font-display text-5xl font-extrabold tabular-nums">{{ $order->queueNumber() }}</p>
            <div class="mt-4 flex justify-center [&_svg]:h-auto [&_svg]:max-w-full" role="img" aria-label="Barcode for order {{ $order->order_number }}">
                {{-- Unescaped on purpose: the barcode library draws this SVG from our own order number. --}}
                {!! $barcode !!}
            </div>
            <p class="mt-2 text-lg tracking-[0.2em]">{{ $order->order_number }}</p>
        </div>

        <div class="my-5 border-t-2 border-dashed border-line"></div>

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

        <ul class="mt-4 space-y-3">
            @foreach ($order->items as $item)
                <li>
                    <div class="flex justify-between gap-4">
                        <span>{{ $item->quantity }} × {{ $item->name }}</span>
                        <span class="tabular-nums"><x-money :amount="$item->line_total" /></span>
                    </div>
                    <p class="text-ink-muted">{{ $item->brand }} · {{ $item->strength }} · <x-money :amount="$item->unit_price" /> each</p>
                </li>
            @endforeach
        </ul>

        <div class="my-5 border-t-2 border-dashed border-line"></div>

        <p class="flex items-baseline justify-between text-lg font-bold">
            <span>TOTAL ({{ $order->item_count }} {{ Str::plural('item', $order->item_count) }})</span>
            <span class="text-2xl tabular-nums"><x-money :amount="$order->total" /></span>
        </p>
        <p class="mt-1 text-ink-muted">Status: {{ $order->status->label() }}</p>

        @if ($order->hasPrescriptionItems())
            <p class="mt-4 rounded-xl bg-rx-soft p-3 text-rx">Contains prescription items. The pharmacist will check them before handover.</p>
        @endif

        <p class="mt-5 text-center text-ink-muted">Thank you. Please keep this slip until you have paid.</p>
    </article>
</div>
</x-layouts.kiosk>
