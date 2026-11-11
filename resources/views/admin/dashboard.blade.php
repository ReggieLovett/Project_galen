<x-layouts.staff title="Staff" :subtitle="$kioskOrdersToday.' kiosk '.Str::plural('order', $kioskOrdersToday).' today'">
    <x-slot:actions>
        <a href="{{ route('admin.medicines.index') }}" class="btn btn-quiet"><x-icon name="pill" />Inventory</a>
    </x-slot:actions>

    {{-- Orders waiting at the till come first: they are what staff act on most. --}}
    <main class="mx-auto max-w-6xl space-y-8 px-6 py-8">
        @session('success')
            <x-alert>{{ $value }}</x-alert>
        @endsession
        @session('error')
            <x-alert type="error">{{ $value }}</x-alert>
        @endsession

        <section class="rounded-2xl border-2 border-line bg-surface">
            <h2 class="flex items-center gap-3 border-b-2 border-line px-5 py-4 text-xl font-bold">
                Orders awaiting payment
                <span class="badge bg-sunken text-base text-ink tabular-nums">{{ $awaitingPayment->count() }}</span>
            </h2>
            @if ($awaitingPayment->isEmpty())
                <p class="px-5 py-8 text-center text-ink-muted">No kiosk orders are waiting at the till.</p>
            @else
                <ul class="divide-y-2 divide-line">
                    @foreach ($awaitingPayment as $order)
                        <li class="flex flex-wrap items-center gap-x-5 gap-y-3 px-5 py-4">
                            <div class="min-w-0 flex-1 basis-72">
                                <p class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <span class="font-mono text-lg font-semibold">{{ $order->order_number }}</span>
                                    <span class="text-ink-muted">· {{ $order->created_at->format('g:i A') }} · {{ $order->user?->name ?? 'Guest' }}</span>
                                    @if ($order->hasPrescriptionItems())
                                        <span class="badge bg-rx-soft text-rx">Prescription · pharmacist check</span>
                                    @endif
                                </p>
                                <p class="mt-1 text-ink-muted">
                                    {{ $order->items->map->summary()->join(', ') }}
                                </p>
                            </div>

                            <p class="font-display text-xl font-bold tabular-nums"><x-money :amount="$order->total" /></p>

                            <div class="flex gap-2">
                                <form method="POST" action="{{ route('admin.orders.complete', $order) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-primary">
                                        <x-icon name="check" />Mark paid<span class="sr-only"> for order {{ $order->order_number }}</span>
                                    </button>
                                </form>
                                <form
                                    method="POST"
                                    action="{{ route('admin.orders.cancel', $order) }}"
                                    x-data
                                    x-on:submit="confirm(@js("Cancel order {$order->order_number}? Its items go back in stock.")) || $event.preventDefault()"
                                >
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit" class="btn btn-quiet text-danger">
                                        <x-icon name="x" />Cancel<span class="sr-only"> order {{ $order->order_number }}</span>
                                    </button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-2xl border-2 border-line bg-surface">
            <h2 class="flex flex-wrap items-center gap-3 border-b-2 border-line px-5 py-4 text-xl font-bold">
                Stock alerts
                <span class="badge bg-danger-soft text-base text-danger tabular-nums">{{ $outOfStock->count() }} out of stock</span>
                <span class="badge bg-rx-soft text-base text-rx tabular-nums">{{ $lowStock->count() }} running low</span>
            </h2>
            {{-- Each alert opens that medicine's form, where its stock is updated. --}}
            <ul class="divide-y-2 divide-line">
                @forelse ($outOfStock->concat($lowStock) as $medicine)
                    <li>
                        <a href="{{ route('admin.medicines.edit', $medicine) }}#stock" class="focus-ring flex min-h-14 items-center justify-between gap-4 px-5 py-3 transition-colors duration-150 hover:bg-sunken">
                            <span class="min-w-0">
                                <span class="font-semibold">{{ $medicine->name }}</span>
                                <span class="text-ink-muted">· {{ $medicine->drugLabel() }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-3">
                                @if ($medicine->isInStock())
                                    <span class="badge bg-rx-soft text-rx">{{ $medicine->countOf(\App\Enums\Packaging::Piece, $medicine->stock) }} left</span>
                                @else
                                    <span class="badge bg-danger-soft text-danger">Out of stock</span>
                                @endif
                                <span class="flex items-center gap-1 font-semibold text-brand">Restock<x-icon name="arrow-right" :size="18" /></span>
                            </span>
                        </a>
                    </li>
                @empty
                    <li class="px-5 py-8 text-center text-ink-muted">Everything is well stocked.</li>
                @endforelse
            </ul>
        </section>
    </main>
</x-layouts.staff>
