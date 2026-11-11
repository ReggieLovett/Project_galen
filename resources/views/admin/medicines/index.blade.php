<x-layouts.staff title="Medicine inventory" section="Inventory">
    <x-slot:actions>
        <a href="{{ route('admin.dashboard') }}" class="btn btn-quiet"><x-icon name="arrow-left" />Orders and stock</a>
        <a href="{{ route('admin.medicines.create') }}" class="btn btn-primary"><x-icon name="plus" />Add medicine</a>
    </x-slot:actions>

    <main class="mx-auto max-w-7xl space-y-6 px-6 py-8 pb-16">
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
                    <li class="flex min-w-0 flex-col overflow-hidden rounded-2xl border-2 border-line bg-surface shadow-[0_12px_30px_-24px_rgb(23_48_45/0.8)] transition-colors duration-200 hover:border-brand/40">
                        <div class="relative">
                            <x-medicine-picture :medicine="$medicine" class="h-32 w-full bg-sunken/50" />
                            <span class="badge absolute top-3 right-3 shadow-sm {{ $medicine->isInStock() ? 'bg-ok-soft text-ok' : 'bg-danger-soft text-danger' }}">
                                {{ $medicine->isInStock() ? 'In stock' : 'Out of stock' }}
                            </span>
                        </div>

                        <div class="flex flex-1 flex-col gap-3 p-4">
                            <div>
                                <div class="flex items-start gap-2">
                                    <div class="min-w-0">
                                        <h2 class="truncate text-lg font-bold" title="{{ $medicine->fullName() }}">{{ $medicine->fullName() }}</h2>
                                        <p class="mt-0.5 truncate text-sm text-ink-muted">{{ $medicine->drugLabel() }} · {{ $medicine->dosage_form }}</p>
                                    </div>
                                    <span class="badge shrink-0 {{ $medicine->category->requires_prescription ? 'bg-rx-soft text-rx' : 'bg-brand-soft text-brand-hover' }}">
                                        {{ $medicine->category->requires_prescription ? 'Prescription' : 'OTC' }}
                                    </span>
                                </div>
                            </div>

                            <dl class="grid grid-cols-2 gap-2">
                                <div class="rounded-xl bg-sunken px-3 py-2">
                                    <dt class="text-sm font-semibold text-ink-muted">Stock</dt>
                                    <dd class="mt-0.5 text-base font-bold tabular-nums">
                                        {{ number_format($medicine->stock) }}
                                        <span class="text-sm font-medium text-ink-muted">pcs</span>
                                    </dd>
                                    {{-- Only when staff need to act; the picture's badge already says in or out of stock. --}}
                                    @if (! $medicine->isInStock())
                                        <p class="text-sm font-semibold text-danger">Restock needed</p>
                                    @elseif ($medicine->isLowStock())
                                        <p class="text-sm font-semibold text-rx">Running low</p>
                                    @endif
                                </div>
                                <div class="rounded-xl bg-sunken px-3 py-2">
                                    <dt class="text-sm font-semibold text-ink-muted">Prices</dt>
                                    <dd class="mt-0.5 text-sm font-semibold tabular-nums"><x-money :amount="$medicine->piece_price" /> <span class="text-sm font-medium text-ink-muted">each</span></dd>
                                    <dd class="text-sm font-semibold tabular-nums"><x-money :amount="$medicine->box_price" /> <span class="text-sm font-medium text-ink-muted">box</span></dd>
                                </div>
                            </dl>

                            <div class="mt-auto flex gap-2 border-t-2 border-line pt-3">
                                <a href="{{ route('admin.medicines.edit', $medicine) }}" class="btn btn-primary min-h-12 flex-1 px-4 text-base" aria-label="Edit {{ $medicine->fullName() }}">Edit</a>
                                <form method="POST" action="{{ route('admin.medicines.destroy', $medicine) }}" x-data x-on:submit="confirm(@js("Remove {$medicine->fullName()} from inventory?")) || $event.preventDefault()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-quiet min-h-12 px-4 text-base text-danger" aria-label="Delete {{ $medicine->fullName() }}">Delete</button>
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

</x-layouts.staff>
