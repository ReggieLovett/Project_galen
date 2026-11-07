@props(['medicine', 'inBasket' => [], 'panel' => null, 'compact' => false])

{{-- One medicine in the catalogue, or (compact) in an assistant reply.
     "Add to basket" opens x-packaging-chooser, where +/- counters pick how
     many strips and boxes; it sends one plain form. "panel" tells the
     controller which drawer to reopen. --}}
@php
    $chooserKey = ($panel ?? 'shelf').'-'.$medicine->id;
    $inBasketSummary = collect($inBasket)
        ->map(fn (int $quantity, string $packaging) => \App\Enums\Packaging::from($packaging)->count($quantity))
        ->join(' and ');
@endphp

<li @if (! $panel) id="medicine-{{ $medicine->id }}" @endif
    {{ $attributes->class([
        'flex scroll-mt-4 flex-col overflow-hidden rounded-2xl border-2 bg-surface transition-[border-color,box-shadow] duration-200',
        'border-brand shadow-[0_10px_30px_-18px_rgb(215_25_32/0.6)]' => $inBasket,
        'border-line' => ! $inBasket,
        'border-dashed' => ! $medicine->isInStock(),
    ]) }}>

    @if ($compact)
        <div class="flex items-start gap-4 p-4">
            <x-medicine-picture :medicine="$medicine" class="size-20 shrink-0 rounded-xl" />
            <div class="min-w-0 flex-1">
                <h3 class="text-lg leading-snug font-bold text-pretty">{{ $medicine->fullName() }}</h3>
                <p class="font-semibold">{{ $medicine->drugLabel() }}</p>
                <p class="text-ink-muted"><x-money :amount="$medicine->strip_price" /> a strip · <x-money :amount="$medicine->box_price" /> a box</p>
                @if ($medicine->description)
                    <p class="mt-1 text-sm leading-snug text-ink-muted">{{ $medicine->description }}</p>
                @endif
                @if ($medicine->requiresPrescription())
                    <p class="mt-2"><span class="badge bg-rx-soft text-rx"><x-icon name="shield-check" :size="16" />Prescription</span></p>
                @endif
            </div>
        </div>
    @else
        <div class="relative">
            <x-medicine-picture :medicine="$medicine" @class(['h-36 w-full', 'grayscale-[0.6] opacity-75' => ! $medicine->isInStock()]) />

            <div class="absolute inset-x-3 top-3 flex items-start justify-between gap-2">
                @if ($medicine->requiresPrescription())
                    <span class="badge bg-rx-soft text-rx shadow-sm"><x-icon name="shield-check" :size="16" />Prescription</span>
                @else
                    <span></span>
                @endif

                @if (! $medicine->isInStock())
                    <span class="badge bg-sunken text-ink-muted shadow-sm">Out of stock</span>
                @elseif ($medicine->isLowStock())
                    <span class="badge bg-rx-soft text-rx shadow-sm">Only {{ \App\Enums\Packaging::Strip->count($medicine->stock) }} left</span>
                @else
                    <span class="badge bg-surface/90 text-ok shadow-sm"><span class="size-2 rounded-full bg-ok"></span>In stock</span>
                @endif
            </div>
        </div>
    @endif

    <div @class(['flex flex-1 flex-col', 'px-4 pb-4' => $compact, 'p-5 pt-4' => ! $compact])>
        @unless ($compact)
            <h3 class="text-xl leading-snug font-bold text-pretty">{{ $medicine->fullName() }}</h3>
            <p class="mt-0.5 text-lg leading-snug font-semibold">{{ $medicine->drugLabel() }}</p>
            <p class="mt-0.5 text-ink-muted">{{ $medicine->dosage_form }} · {{ $medicine->pieces_per_strip }} per strip</p>

            @if ($medicine->description)
                {{-- What it's for, in plain words --}}
                <p class="mt-3 border-l-4 border-brand-soft pl-3 leading-snug">
                    <span class="sr-only">What it's for: </span>{{ $medicine->description }}
                </p>
            @endif

            <div class="mt-auto flex items-end justify-between gap-3 pt-5">
                <p class="leading-tight">
                    <span class="block text-sm text-ink-muted">Per strip</span>
                    <x-money :amount="$medicine->strip_price" class="font-display text-2xl font-extrabold whitespace-nowrap" />
                </p>
                <p class="text-right leading-tight">
                    <span class="block text-sm text-ink-muted">Box of {{ $medicine->strips_per_box }} strips</span>
                    <x-money :amount="$medicine->box_price" class="font-display text-lg font-bold whitespace-nowrap" />
                </p>
            </div>
        @endunless

        {{ $slot }}

        @if ($inBasket)
            <p class="mt-3 flex items-center gap-1.5 font-semibold text-brand">
                <x-icon name="check" :size="18" />{{ $inBasketSummary }} in your basket
            </p>
        @endif

        @if (! $medicine->isInStock())
            <form method="POST" action="{{ route('assistant.store') }}" class="mt-4">
                @csrf
                <input type="hidden" name="medicine_id" value="{{ $medicine->id }}">
                <button type="submit" class="btn btn-assist w-full"><x-icon name="sparkles" />Find alternatives</button>
            </form>
        @elsecan('addToBasket', $medicine)
            <button type="button" x-on:click="pack = @js($chooserKey)" class="btn btn-primary {{ $compact ? 'mt-3' : 'mt-4' }} w-full">
                <x-icon name="plus" />{{ $inBasket ? 'Add more' : 'Add to basket' }}
            </button>
            <x-packaging-chooser :medicine="$medicine" :key="$chooserKey" :panel="$panel" :in-basket="$inBasket" />
        @else
            {{-- Needs a prescription on file: the controller answers with the "Prescription required" popup. --}}
            <form method="POST" action="{{ route('basket.store', $medicine) }}" class="{{ $compact ? 'mt-3' : 'mt-4' }}">
                @csrf
                @if ($panel) <input type="hidden" name="panel" value="{{ $panel }}"> @endif
                <button type="submit" class="btn btn-primary w-full"><x-icon name="plus" />Add to basket</button>
            </form>
        @endif
    </div>
</li>
