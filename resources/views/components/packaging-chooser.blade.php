@props(['medicine', 'panel' => null, 'inBasket' => []])

{{--
    "How many?" right on the medicine's card: one counter for single pieces
    (tablets, capsules) and one for whole boxes, both starting at 0, so a
    customer can take 11 tablets and the next one 3. + and - change them,
    and "Add to basket" sends one plain form with quantities[piece] and
    quantities[box] to basket.store, where the controller validates and
    stock-checks it all again.

    The counters (packCounter in resources/js/app.js) know that pieces and
    boxes share the shelf stock, and what is already in the basket.
--}}
@use('App\Enums\Packaging')

@php
    $limit = config('kiosk.max_quantity_per_item');
    $alreadyHeld = collect($inBasket)->sum(fn (int $quantity, string $packaging) => $quantity * $medicine->piecesPer(Packaging::from($packaging)));

    $counter = [
        'shelf' => max(0, $medicine->stock - $alreadyHeld),
        'piecesPerBox' => $medicine->piecesPerBox(),
        // null: no per-item limit, only shelf stock.
        'lineLeft' => collect(Packaging::cases())->mapWithKeys(fn (Packaging $packaging) => [
            $packaging->value => $limit === null ? null : max(0, $limit - ($inBasket[$packaging->value] ?? 0)),
        ]),
        'prices' => collect(Packaging::cases())->mapWithKeys(fn (Packaging $packaging) => [$packaging->value => (float) $medicine->priceOf($packaging)]),
        'currency' => config('kiosk.currency'),
    ];
@endphp

<form
    method="POST"
    action="{{ route('basket.store', $medicine) }}"
    x-data="packCounter(@js($counter))"
    {{ $attributes->class('@container flex flex-col gap-2') }}
>
    @csrf
    @if ($panel) <input type="hidden" name="panel" value="{{ $panel }}"> @endif

    @foreach (Packaging::cases() as $packaging)
        @php
            $value = $packaging->value;
            $one = $packaging === Packaging::Box ? 'box' : $medicine->pieceName(1);
            $many = $packaging === Packaging::Box ? 'boxes' : $medicine->pieceName();
        @endphp

        {{-- On a narrow card the +/- sits under the label; on a wide one, beside it. --}}
        <div
            class="flex flex-col gap-1 rounded-xl border-2 p-1.5 pl-3 transition-colors duration-150 @min-[18rem]:flex-row @min-[18rem]:items-center @min-[18rem]:gap-2"
            x-bind:class="counts.{{ $value }} > 0 ? 'border-brand bg-brand-soft/50' : 'border-line'"
        >
            <div class="flex min-w-0 flex-1 flex-wrap items-baseline justify-between gap-x-2 pt-1 pr-1.5 leading-tight @min-[18rem]:block @min-[18rem]:pt-0 @min-[18rem]:pr-0">
                <p class="font-display font-bold whitespace-nowrap">
                    {{ $packaging === Packaging::Box ? "Box of {$medicine->piecesPerBox()}" : Str::ucfirst($many) }}
                </p>
                <p class="font-semibold whitespace-nowrap"><x-money :amount="$medicine->priceOf($packaging)" />{{ $packaging === Packaging::Piece ? ' each' : '' }}</p>
                @if ($packaging === Packaging::Box && ! $medicine->canSell($packaging))
                    <p class="basis-full text-xs font-semibold text-ink-muted">Not enough left for a box</p>
                @elseif ($packaging === Packaging::Box && $medicine->boxSavings() > 0)
                    <p class="basis-full text-xs font-semibold text-brand-hover">Save <x-money :amount="$medicine->boxSavings()" /> on {{ $medicine->piecesPerBox() }} single {{ $medicine->pieceName() }}</p>
                @endif
            </div>

            {{-- The +/- counter. The hidden input is what the form sends. --}}
            <div class="flex shrink-0 items-center justify-between" role="group" aria-label="How many {{ $many }} of {{ $medicine->name }}">
                <button type="button" x-on:click="step('{{ $value }}', -1)" x-bind:disabled="counts.{{ $value }} === 0" disabled class="btn-icon size-11" aria-label="One {{ $one }} fewer of {{ $medicine->name }}">
                    <x-icon name="minus" :size="20" />
                </button>
                <output class="min-w-10 px-1 text-center font-display text-2xl font-extrabold tabular-nums" x-text="counts.{{ $value }}" aria-live="polite">0</output>
                <button type="button" x-on:click="step('{{ $value }}', 1)" x-bind:disabled="counts.{{ $value }} >= max('{{ $value }}')" class="btn-icon size-11 border-brand bg-brand text-white hover:bg-brand-hover" aria-label="One more {{ $one }} of {{ $medicine->name }}">
                    <x-icon name="plus" :size="20" />
                </button>
            </div>
            <input type="hidden" name="quantities[{{ $value }}]" value="0" x-bind:value="counts.{{ $value }}">
        </div>
    @endforeach

    <p x-show="atLimit" x-cloak class="text-sm font-semibold text-ink-muted">That's all we have on the shelf right now.</p>

    <button type="submit" x-bind:disabled="empty" disabled class="btn btn-primary mt-1 w-full">
        <x-icon name="basket" />
        <span x-text="empty ? 'Add to basket' : `Add to basket · ${money(total)}`">Add to basket</span>
    </button>
</form>
