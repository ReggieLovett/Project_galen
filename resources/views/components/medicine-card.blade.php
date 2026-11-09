@props(['medicine', 'inBasket' => [], 'panel' => null, 'compact' => false])

{{-- One medicine in the catalogue, or (compact) in an assistant reply.
     The card shows the medicine first. Tapping its picture or "What is it
     for?" opens the plain-language description; scrolling the catalogue
     closes it again (medicineDetails in resources/js/app.js). The +/-
     counters (x-packaging-chooser) are on the card itself: single pieces
     and whole boxes, both starting at 0. "panel" tells the controller
     which drawer to reopen. --}}
@php
    $key = ($panel ?? 'shelf').'-'.$medicine->id;
    $canCount = $medicine->isInStock() && \Illuminate\Support\Facades\Gate::allows('addToBasket', $medicine);
    $inBasketSummary = collect($inBasket)
        ->map(fn (int $quantity, string $packaging) => $medicine->countOf(\App\Enums\Packaging::from($packaging), $quantity))
        ->join(' and ');
@endphp

<li @if (! $panel) id="medicine-{{ $medicine->id }}" @endif
    x-data="medicineDetails(@js($key))"
    x-on:catalogue-scroll.window="scrolled($event.detail)"
    x-on:details-opened.window="$event.detail !== key && (open = false)"
    {{ $attributes->class([
        '@container flex scroll-mt-4 flex-col overflow-hidden rounded-2xl border-2 bg-surface transition-[border-color,box-shadow] duration-200',
        'border-brand shadow-[0_10px_30px_-18px_rgb(215_25_32/0.6)]' => $inBasket,
        'border-line' => ! $inBasket,
        'border-dashed' => ! $medicine->isInStock(),
    ]) }}>

    @if ($compact)
        <div class="flex items-start gap-4 p-4">
            <x-medicine-picture :medicine="$medicine" x-on:click="toggle()" class="size-20 shrink-0 cursor-pointer rounded-xl" />
            <div class="min-w-0 flex-1">
                <h3 id="name-{{ $key }}" class="text-lg leading-snug font-bold text-pretty">{{ $medicine->fullName() }}</h3>
                <p class="font-semibold">{{ $medicine->drugLabel() }}</p>
                @unless ($canCount)
                    <p class="text-ink-muted"><x-money :amount="$medicine->piece_price" /> a {{ $medicine->pieceName(1) }} · <x-money :amount="$medicine->box_price" /> a box</p>
                @endunless
                @if ($medicine->requiresPrescription())
                    <p class="mt-2"><span class="badge bg-rx-soft text-rx"><x-icon name="shield-check" :size="16" />Prescription</span></p>
                @endif
            </div>
        </div>
    @else
        <div class="relative cursor-pointer" x-on:click="toggle()">
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
                    <span class="badge bg-rx-soft text-rx shadow-sm">Only {{ $medicine->countOf(\App\Enums\Packaging::Piece, $medicine->stock) }} left</span>
                @else
                    <span class="badge bg-surface/90 text-ok shadow-sm"><span class="size-2 rounded-full bg-ok"></span>In stock</span>
                @endif
            </div>
        </div>
    @endif

    <div @class(['flex flex-1 flex-col', 'px-4 pb-4' => $compact, 'p-5 pt-4' => ! $compact])>
        @unless ($compact)
            <h3 id="name-{{ $key }}" class="text-xl leading-snug font-bold text-pretty">{{ $medicine->fullName() }}</h3>
            <p class="mt-0.5 text-lg leading-snug font-semibold">{{ $medicine->drugLabel() }}</p>
            <p class="mt-0.5 text-ink-muted">{{ $medicine->dosage_form }} · box of {{ $medicine->piecesPerBox() }}</p>
        @endunless

        @if ($medicine->description)
            {{-- What it's for, in plain words: hidden until the customer asks --}}
            <button
                type="button"
                x-on:click="toggle()"
                x-bind:aria-expanded="open"
                aria-controls="uses-{{ $key }}"
                class="focus-ring mt-2 inline-flex min-h-11 items-center gap-1.5 self-start rounded-lg font-semibold text-brand"
            >
                <x-icon name="info" :size="18" />
                <span x-text="open ? 'Hide details' : 'What is it for?'">What is it for?</span>
            </button>
            <p id="uses-{{ $key }}" x-show="open" x-cloak x-transition.opacity.duration.150ms class="mt-1 rounded-xl bg-sunken p-3 leading-snug">
                {{ $medicine->description }}
            </p>
        @endif

        @unless ($compact || $canCount)
            <div class="mt-auto flex items-end justify-between gap-3 pt-5">
                <p class="leading-tight">
                    <span class="block text-sm text-ink-muted">Per {{ $medicine->pieceName(1) }}</span>
                    <x-money :amount="$medicine->piece_price" class="font-display text-2xl font-extrabold whitespace-nowrap" />
                </p>
                <p class="text-right leading-tight">
                    <span class="block text-sm text-ink-muted">Box of {{ $medicine->piecesPerBox() }}</span>
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
                <button type="submit" class="btn btn-assist w-full @max-[18rem]:px-3" aria-describedby="name-{{ $key }}"><x-icon name="sparkles" />Find alternatives</button>
            </form>
        @elseif ($canCount)
            @if ($medicine->requiresPrescription())
                <p @class(['flex items-center gap-1.5 font-semibold text-rx', 'mt-auto pt-4' => ! $compact, 'mt-3' => $compact])>
                    <x-icon name="shield-check" :size="18" />Prescription on file
                </p>
            @endif
            <x-packaging-chooser :medicine="$medicine" :panel="$panel" :in-basket="$inBasket" @class([
                'mt-auto pt-4' => ! $compact && ! $medicine->requiresPrescription(),
                'pt-2' => ! $compact && $medicine->requiresPrescription(),
                'mt-3' => $compact && ! $medicine->requiresPrescription(),
                'mt-2' => $compact && $medicine->requiresPrescription(),
            ]) />
        @else
            {{-- A prescription medicine this customer can't add here. Say why before they
                 tap anything, and offer the one thing that can change it: a member card
                 with this drug in its purchase history (MedicinePolicy). --}}
            <div @class(['flex gap-2.5 rounded-xl bg-rx-soft p-3 text-rx', 'mt-4' => ! $compact, 'mt-3' => $compact])>
                <x-icon name="shield-alert" :size="22" />
                <div class="leading-snug">
                    <p class="font-display text-lg font-bold">Prescription required</p>
                    @auth
                        <p class="mt-0.5">We have no record of you buying this before. Please ask the pharmacist at the counter.</p>
                    @else
                        <p class="mt-0.5">Filled it with us before? Sign in with your member card to add a refill. If not, please ask the pharmacist at the counter.</p>
                    @endauth
                </div>
            </div>
            @guest
                <button type="button" x-on:click="refillFor = {{ $medicine->id }}; panel = 'member'" class="btn btn-quiet mt-3 w-full @max-[18rem]:px-3" aria-describedby="name-{{ $key }}">
                    <x-icon name="card" />Sign in to add a refill
                </button>
            @endguest
        @endif
    </div>
</li>
