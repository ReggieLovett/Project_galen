<x-layouts.kiosk title="Medicine inventory" noindex>
    <header class="flex flex-wrap items-center gap-4 border-b-2 border-line bg-surface px-6 py-4">
        <a href="{{ route('admin.dashboard') }}" class="focus-ring rounded-xl" aria-label="Back to staff dashboard">
            <x-logo-mark class="size-12 rounded-xl bg-brand text-white" />
        </a>
        <div class="min-w-0 flex-1">
            <p class="font-display text-xl font-bold">{{ config('kiosk.name') }} · Inventory</p>
            <p class="text-ink-muted">Signed in as {{ auth('admin')->user()->name }}</p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('admin.dashboard') }}" class="btn btn-quiet">Dashboard</a>
            <a href="{{ route('admin.medicines.create') }}" class="btn btn-primary"><x-icon name="plus" />Add medicine</a>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button type="submit" class="btn btn-quiet"><x-icon name="log-out" />Log out</button>
            </form>
        </div>
    </header>

    <main class="mx-auto min-h-dvh max-w-7xl space-y-6 px-6 py-8 pb-28">
        @session('success')
            <x-alert>{{ $value }}</x-alert>
        @endsession
        @session('error')
            <x-alert type="error">{{ $value }}</x-alert>
        @endsession

        <section>
            <div class="mb-5 flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold">All medicines</h1>
                    <p class="mt-1 text-ink-muted">
                        {{ $medicines->total() }} {{ \Illuminate\Support\Str::plural('product', $medicines->total()) }}{{ filled($search) ? ' matching your search' : ' in the database' }}
                    </p>
                </div>
                <p class="text-sm text-ink-muted">Low stock means {{ \App\Models\Medicine::LOW_STOCK }} pieces or fewer.</p>
            </div>

            <form method="GET" action="{{ route('admin.medicines.index') }}" class="mb-6 flex flex-col gap-3 sm:flex-row">
                <label for="medicine-search" class="sr-only">Search medicines</label>
                <input
                    id="medicine-search"
                    name="search"
                    type="search"
                    value="{{ $search }}"
                    placeholder="Search by name, brand, ingredient, or class"
                    class="focus-ring h-12 min-w-0 flex-1 rounded-xl border-2 border-line bg-surface px-4 text-base shadow-sm placeholder:text-ink-muted/80"
                >
                <button type="submit" class="btn btn-primary min-h-12 justify-center px-5">
                    <x-icon name="search" />
                    Search
                </button>
                @if (filled($search))
                    <a href="{{ route('admin.medicines.index') }}" class="btn btn-quiet min-h-12 justify-center">Clear</a>
                @endif
            </form>

            <ul class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4" aria-label="Medicine inventory">
                @forelse ($medicines as $medicine)
                    <li class="flex min-w-0 flex-col overflow-hidden rounded-2xl border-2 border-line bg-surface shadow-[0_12px_30px_-24px_rgb(23_48_45/0.8)] transition-[border-color,box-shadow,transform] duration-200 hover:-translate-y-1 hover:border-brand/40 hover:shadow-[0_18px_34px_-24px_rgb(15_118_110/0.65)]">
                        <div class="relative">
                            <x-medicine-picture :medicine="$medicine" class="h-32 w-full bg-sunken/50" />
                            <span class="absolute top-3 right-3 rounded-lg px-2 py-1 text-xs font-semibold shadow-sm {{ $medicine->isInStock() ? 'bg-ok-soft text-ok' : 'bg-danger-soft text-danger' }}">
                                {{ $medicine->isInStock() ? 'Available' : 'Unavailable' }}
                            </span>
                        </div>

                        <div class="flex flex-1 flex-col gap-3 p-4">
                            <div>
                                <div class="flex items-start gap-2">
                                    <div class="min-w-0">
                                        <h2 class="truncate text-lg font-bold" title="{{ $medicine->fullName() }}">{{ $medicine->fullName() }}</h2>
                                        <p class="mt-0.5 truncate text-xs text-ink-muted">{{ $medicine->drugLabel() }} · {{ $medicine->dosage_form }}</p>
                                    </div>
                                    <span class="shrink-0 rounded-lg px-2 py-1 text-xs font-semibold {{ $medicine->category->requires_prescription ? 'bg-rx-soft text-rx' : 'bg-brand-soft text-brand-hover' }}">
                                        {{ $medicine->category->requires_prescription ? 'Prescription' : 'OTC' }}
                                    </span>
                                </div>
                            </div>

                            <dl class="grid grid-cols-2 gap-2">
                                <div class="rounded-xl bg-sunken px-3 py-2">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Stock</dt>
                                    <dd class="mt-0.5 text-base font-bold tabular-nums">
                                        {{ number_format($medicine->stock) }}
                                        <span class="text-xs font-medium text-ink-muted">pcs</span>
                                    </dd>
                                    @if (! $medicine->isInStock())
                                        <p class="text-[11px] font-semibold text-danger">Restock needed</p>
                                    @elseif ($medicine->isLowStock())
                                        <p class="text-[11px] font-semibold text-rx">Running low</p>
                                    @else
                                        <p class="text-[11px] font-semibold text-ok">Ready to buy</p>
                                    @endif
                                </div>
                                <div class="rounded-xl bg-sunken px-3 py-2">
                                    <dt class="text-xs font-semibold uppercase tracking-wide text-ink-muted">Prices</dt>
                                    <dd class="mt-0.5 text-sm font-semibold tabular-nums"><x-money :amount="$medicine->piece_price" /> <span class="text-[11px] font-medium text-ink-muted">each</span></dd>
                                    <dd class="text-xs font-semibold tabular-nums"><x-money :amount="$medicine->box_price" /> <span class="text-[11px] font-medium text-ink-muted">box</span></dd>
                                </div>
                            </dl>

                            <div class="mt-auto flex gap-2 border-t-2 border-line pt-3">
                                <a href="{{ route('admin.medicines.edit', $medicine) }}" class="btn btn-primary min-h-10 flex-1 px-3 text-sm">Edit</a>
                                <form method="POST" action="{{ route('admin.medicines.destroy', $medicine) }}" x-data x-on:submit="confirm(@js("Remove {$medicine->fullName()} from inventory?")) || $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-quiet min-h-10 px-3 text-sm text-danger" aria-label="Delete {{ $medicine->fullName() }}">Delete</button>
                                </form>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="col-span-full rounded-3xl border-2 border-dashed border-line bg-surface px-6 py-16 text-center">
                        <x-icon name="pill" :size="40" class="mx-auto text-ink-muted" />
                        <h2 class="mt-4 text-xl font-bold">{{ filled($search) ? 'No matching medicines' : 'No medicines yet' }}</h2>
                        <p class="mt-2 text-ink-muted">
                            {{ filled($search) ? 'Try a different name, brand, ingredient, or therapeutic class.' : 'Add the first medicine to make it available for kiosk customers.' }}
                        </p>
                        @if (filled($search))
                            <a href="{{ route('admin.medicines.index') }}" class="btn btn-quiet mt-5">Clear search</a>
                        @else
                            <a href="{{ route('admin.medicines.create') }}" class="btn btn-primary mt-5">Add medicine</a>
                        @endif
                    </li>
                @endforelse
            </ul>

            @if ($medicines->hasPages())
                <div class="border-t-2 border-line px-5 py-4 pb-8">{{ $medicines->links() }}</div>
            @endif
        </section>
    </main>

    <x-idle-timer leave="Log out now">
        For security, you'll be logged out and the kiosk will go back to the start screen in <strong class="text-ink tabular-nums" x-text="`${remaining} seconds`"></strong>.
    </x-idle-timer>
</x-layouts.kiosk>
