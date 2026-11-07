@props(['medicine', 'key', 'panel' => null, 'inBasket' => []])

{{--
    "How many strips and boxes?" Opened by setting the kiosk's `pack` to this
    key. The customer counts strips and boxes with +/- and adds both in one
    tap: a single form posting quantities[strip] and quantities[box] to
    basket.store, where the controller validates and stock-checks it all
    again. Teleported to <body> so it sits above the assistant drawer.

    The counters (packCounter in resources/js/app.js) know that strips and
    boxes share the shelf stock, and what is already in the basket.
--}}
@use('App\Enums\Packaging')

@php
    $limit = config('kiosk.max_quantity_per_item');
    $alreadyHeld = collect($inBasket)->sum(fn (int $quantity, string $packaging) => $quantity * $medicine->stripsPer(Packaging::from($packaging)));

    $counter = [
        'shelf' => max(0, $medicine->stock - $alreadyHeld),
        'stripsPerBox' => $medicine->strips_per_box,
        'lineLeft' => collect(Packaging::cases())->mapWithKeys(fn (Packaging $packaging) => [$packaging->value => max(0, $limit - ($inBasket[$packaging->value] ?? 0))]),
        'prices' => collect(Packaging::cases())->mapWithKeys(fn (Packaging $packaging) => [$packaging->value => (float) $medicine->priceOf($packaging)]),
        'currency' => config('kiosk.currency'),
    ];
@endphp

<template x-teleport="body">
    <div>
        <template x-if="pack === @js($key)">
            <div x-trap.noscroll="true" class="fixed inset-0 z-[70] flex items-center justify-center p-4 sm:p-5" role="dialog" aria-modal="true" aria-labelledby="{{ $key }}-title" aria-describedby="{{ $key }}-uses">
                <div x-on:click="pack = null" class="absolute inset-0 bg-ink/60"></div>

                <form
                    method="POST"
                    action="{{ route('basket.store', $medicine) }}"
                    x-data="packCounter(@js($counter))"
                    class="relative flex max-h-full w-full max-w-2xl flex-col overflow-hidden rounded-3xl bg-surface"
                >
                    @csrf
                    @if ($panel) <input type="hidden" name="panel" value="{{ $panel }}"> @endif

                    <div class="min-h-0 overflow-y-auto p-6 sm:p-8">
                        <div class="flex items-start gap-4">
                            <x-medicine-picture :medicine="$medicine" show="box" class="size-20 shrink-0 rounded-2xl" />
                            <div class="min-w-0 flex-1">
                                <p class="font-display text-lg font-semibold text-brand">How many would you like?</p>
                                <h2 id="{{ $key }}-title" class="text-2xl leading-tight font-bold text-pretty">{{ $medicine->fullName() }}</h2>
                                <p class="mt-0.5 text-ink-muted">{{ $medicine->drugLabel() }} · {{ $medicine->dosage_form }}</p>
                            </div>
                            <button type="button" x-on:click="pack = null" class="btn-icon" aria-label="Close"><x-icon name="x" /></button>
                        </div>

                        @if ($medicine->description)
                            <p id="{{ $key }}-uses" class="mt-4 flex gap-2.5 rounded-2xl bg-sunken p-4 text-lg leading-snug">
                                <x-icon name="info" class="mt-0.5 text-brand" />
                                <span><span class="font-semibold">What it's for:</span> {{ $medicine->description }}</span>
                            </p>
                        @endif

                        <div class="mt-5 grid gap-4 sm:grid-cols-2">
                            @foreach (Packaging::cases() as $packaging)
                                @php
                                    $value = $packaging->value;
                                    $plural = Str::plural(Str::lower($packaging->label()));
                                @endphp
                                <div
                                    class="flex flex-col rounded-2xl border-2 p-4 transition-colors duration-150"
                                    x-bind:class="counts.{{ $value }} > 0 ? 'border-brand bg-brand-soft/40' : 'border-line'"
                                    x-bind:aria-disabled="max('{{ $value }}') === 0 && counts.{{ $value }} === 0"
                                >
                                    <x-medicine-picture :medicine="$medicine" :show="$value" class="h-24 w-full rounded-xl" />

                                    <div class="mt-3 flex items-center justify-between gap-3">
                                        <p class="font-display text-xl font-bold">{{ Str::ucfirst($plural) }}</p>
                                        @if ($packaging === Packaging::Box && $medicine->boxSavings() > 0)
                                            <span class="badge bg-brand-soft text-brand-hover">Save <x-money :amount="$medicine->boxSavings()" /> a box</span>
                                        @endif
                                    </div>
                                    <p class="text-ink-muted">{{ $medicine->packDescription($packaging) }}</p>
                                    <p class="mt-1 font-semibold"><x-money :amount="$medicine->priceOf($packaging)" class="font-display text-2xl font-extrabold" /> each</p>

                                    {{-- The +/- counter. The hidden input is what the form sends. --}}
                                    <div class="mt-auto flex items-center justify-between gap-2 pt-4" role="group" aria-label="How many {{ $plural }}">
                                        <button type="button" x-on:click="step('{{ $value }}', -1)" x-bind:disabled="counts.{{ $value }} === 0" class="btn-icon size-16" aria-label="One {{ Str::lower($packaging->label()) }} fewer">
                                            <x-icon name="minus" :size="28" />
                                        </button>
                                        <output class="min-w-12 text-center font-display text-4xl font-extrabold tabular-nums" x-text="counts.{{ $value }}" aria-live="polite">0</output>
                                        <button type="button" x-on:click="step('{{ $value }}', 1)" x-bind:disabled="counts.{{ $value }} >= max('{{ $value }}')" class="btn-icon size-16 border-brand bg-brand text-white hover:bg-brand-hover" aria-label="One more {{ Str::lower($packaging->label()) }}">
                                            <x-icon name="plus" :size="28" />
                                        </button>
                                    </div>
                                    <input type="hidden" name="quantities[{{ $value }}]" x-bind:value="counts.{{ $value }}">

                                    <p x-show="counts.{{ $value }} > 0 && counts.{{ $value }} >= max('{{ $value }}')" x-cloak class="mt-3 text-sm font-semibold text-ink-muted">
                                        @if ($packaging === Packaging::Box)
                                            That's as many boxes as we can sell you right now.
                                        @else
                                            That's as many strips as we can sell you right now.
                                        @endif
                                    </p>
                                    @if ($packaging === Packaging::Box && ! $medicine->canSell($packaging))
                                        <p class="mt-3 flex items-center gap-1.5 text-sm font-semibold text-ink-muted">
                                            <x-icon name="info" :size="16" />Only {{ Packaging::Strip->count($medicine->stock) }} left, not enough for a box
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>

                    {{-- Running total and the one button that adds it all --}}
                    <div class="flex flex-col gap-3 border-t-2 border-line bg-surface p-5 sm:flex-row sm:items-center sm:px-8">
                        <div class="min-w-0 flex-1" aria-live="polite">
                            <p class="font-display text-lg font-semibold" x-text="summary"></p>
                            <p class="font-display text-3xl font-extrabold tabular-nums" x-text="money(total)"></p>
                        </div>
                        <button type="button" x-on:click="pack = null" class="btn btn-quiet">Cancel</button>
                        <button type="submit" x-bind:disabled="empty" class="btn btn-primary min-h-16 px-8 text-xl">
                            <x-icon name="basket" />Add to basket
                        </button>
                    </div>
                </form>
            </div>
        </template>
    </div>
</template>
