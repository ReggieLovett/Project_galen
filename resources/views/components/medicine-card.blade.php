@props(['medicine', 'inBasket' => 0, 'panel' => null, 'compact' => false])

{{-- One medicine in the catalogue, or (compact) in an assistant reply. Its
     buttons are plain forms; "panel" tells the controller which drawer to reopen. --}}
<li @if (! $panel) id="medicine-{{ $medicine->id }}" @endif
    {{ $attributes->class([
        'flex scroll-mt-4 flex-col rounded-2xl border-2 bg-surface',
        $compact ? 'p-4' : 'p-5',
        'border-brand' => $inBasket,
        'border-line' => ! $inBasket,
        'border-dashed' => ! $medicine->isInStock(),
    ]) }}>
    <div class="flex items-start justify-between gap-4">
        <div class="min-w-0">
            <h3 class="{{ $compact ? 'text-lg' : 'text-xl' }} leading-snug font-bold text-pretty">{{ $medicine->fullName() }}</h3>
            <p class="mt-1 text-lg font-semibold">{{ $medicine->drugLabel() }}</p>
            <p class="text-ink-muted">{{ $medicine->dosage_form }} · {{ $medicine->pack_size }}</p>
        </div>

        @if ($compact)
            <x-money :amount="$medicine->price" class="shrink-0 font-display text-xl font-bold" />
        @endif
    </div>

    @if ($medicine->requiresPrescription())
        <p class="mt-3"><span class="badge bg-rx-soft text-rx"><x-icon name="shield-check" :size="16" />Prescription</span></p>
    @endif

    {{ $slot }}

    @unless ($compact)
        <div class="mt-auto flex items-end justify-between gap-3 pt-5">
            <x-money :amount="$medicine->price" class="font-display text-2xl font-bold" />
            @if (! $medicine->isInStock())
                <span class="badge bg-danger-soft text-danger">Out of stock</span>
            @elseif ($medicine->isLowStock())
                <span class="badge bg-rx-soft text-rx">Only {{ $medicine->stock }} left</span>
            @else
                <span class="flex items-center gap-1.5 font-semibold text-brand"><span class="size-2.5 rounded-full bg-brand"></span>In stock</span>
            @endif
        </div>
    @endunless

    @if ($inBasket)
        <p class="mt-3 flex items-center gap-1.5 font-semibold text-brand-hover">
            <x-icon name="check" :size="18" />{{ $inBasket }} in your basket
        </p>
    @endif

    @if ($medicine->isInStock())
        <form method="POST" action="{{ route('basket.store', $medicine) }}" class="{{ $compact ? 'mt-3' : 'mt-4' }}">
            @csrf
            @if ($panel) <input type="hidden" name="panel" value="{{ $panel }}"> @endif
            <button type="submit" class="btn btn-primary w-full"><x-icon name="plus" />{{ $inBasket ? 'Add another' : 'Add to basket' }}</button>
        </form>
    @else
        <form method="POST" action="{{ route('assistant.store') }}" class="mt-4">
            @csrf
            <input type="hidden" name="medicine_id" value="{{ $medicine->id }}">
            <button type="submit" class="btn btn-assist w-full"><x-icon name="sparkles" />Find alternatives</button>
        </form>
    @endif
</li>
