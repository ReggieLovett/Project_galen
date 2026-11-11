@php
    // Filter links keep the other filters: route('kiosk', ['q' => 'advil', 'type' => 'otc'])
    $filterUrl = fn (array $changes) => route('kiosk', array_filter(array_merge($filters, $changes)));

    // Which drawer or popup to show after a redirect back here.
    $openPanel = session('panel')
        ?? ($errors->has('member_number') ? 'member' : ($errors->has('question') ? 'assistant' : null));

    $rx = session('prescription_required');
@endphp

<x-layouts.kiosk title="Shop" class="overflow-hidden">
{{-- panel: which drawer is open. refillFor: the prescription medicine to go back to after a member signs in. --}}
<div
    x-data="{ panel: @js($openPanel), refillFor: @js($rx['medicine_id'] ?? null), showBackToTop: false }"
    x-on:keydown.escape.window="panel = null"
    x-on:catalogue-scroll.window="showBackToTop = $event.detail > 420"
    x-on:catalogue-scroll-to-top.window="document.querySelector('[data-catalogue]')?.scrollTo({ top: 0, behavior: 'smooth' })"
    class="flex h-dvh flex-col"
>

    {{-- ================= Top bar ================= --}}
    <header class="flex flex-wrap items-center gap-3 border-b-4 border-brand-deep bg-brand px-4 py-3 text-white sm:flex-nowrap sm:gap-4 sm:px-6 sm:py-4">
        <a href="{{ route('kiosk') }}" class="focus-ring flex shrink-0 items-center gap-3 rounded-xl" aria-label="{{ config('kiosk.name') }}, all medicines">
            <x-logo-mark class="size-12 rounded-xl bg-white text-brand" />
            <span class="hidden font-display text-xl leading-tight font-bold xl:block">{{ config('kiosk.name') }}</span>
        </a>

        {{-- SEARCH — a plain GET form: /kiosk?q=advil --}}
        <form method="GET" action="{{ route('kiosk') }}" role="search" class="relative order-last min-w-0 basis-full sm:order-none sm:flex-1 sm:basis-auto">
            @foreach (['type', 'class'] as $keep)
                @if (filled($filters[$keep] ?? null))
                    <input type="hidden" name="{{ $keep }}" value="{{ $filters[$keep] }}">
                @endif
            @endforeach

            <label for="search" class="sr-only">Search medicines</label>
            <x-icon name="search" class="pointer-events-none absolute top-1/2 left-5 -translate-y-1/2 text-ink-muted" />
            <input
                id="search"
                type="search"
                name="q"
                value="{{ $filters['q'] ?? '' }}"
                maxlength="60"
                inputmode="none"
                autocomplete="off"
                spellcheck="false"
                placeholder="Search by name, brand or ingredient"
                x-on:pointerdown="$event.pointerType === 'touch' && $dispatch('keyboard-show', 'search')"
                class="focus-ring h-14 w-full rounded-xl border-2 border-transparent bg-surface pr-36 pl-14 text-lg text-ellipsis text-ink placeholder:text-ink-muted focus:border-white"
            >
            <div class="absolute top-1/2 right-2 flex -translate-y-1/2 items-center gap-1">
                @if (filled($filters['q'] ?? null))
                    <a href="{{ $filterUrl(['q' => null]) }}" class="focus-ring flex size-11 items-center justify-center rounded-xl text-ink-muted hover:bg-sunken" aria-label="Clear search">
                        <x-icon name="x" />
                    </a>
                @endif
                <button type="button" x-on:click="$dispatch('keyboard-toggle', 'search')" class="focus-ring flex size-11 items-center justify-center rounded-xl text-ink-muted hover:bg-sunken" aria-label="On-screen keyboard">
                    <x-icon name="keyboard" />
                </button>
                <button type="submit" class="focus-ring flex size-11 items-center justify-center rounded-xl bg-brand text-white hover:bg-brand-hover" aria-label="Search">
                    <x-icon name="arrow-right" />
                </button>
            </div>
        </form>

        <div class="ml-auto flex shrink-0 items-center gap-2 sm:ml-0 sm:gap-3">
        {{-- Always labelled, so customers can see where to ask for help --}}
        <button type="button" x-on:click="panel = 'assistant'" class="btn shrink-0 bg-white px-4 text-assist hover:bg-assist-soft xl:px-6">
            <x-icon name="sparkles" />
            <span>Ask<span class="hidden lg:inline"> the assistant</span></span>
        </button>

        {{-- Shown only when a member card is signed in --}}
        @auth
            <div class="flex shrink-0 items-center gap-2">
                <span class="hidden items-center gap-2 rounded-xl bg-white/15 px-4 py-3 font-display text-lg font-semibold text-white xl:flex">
                    <x-icon name="user" />Hi, {{ auth()->user()->firstName() }}
                </span>
                <form method="POST" action="{{ route('member.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-icon border-white/40 bg-white/10 text-white hover:bg-white/20" aria-label="Sign out of member card"><x-icon name="log-out" /></button>
                </form>
            </div>
        @else
            <button type="button" x-on:click="refillFor = null; panel = 'member'" class="btn shrink-0 border-2 border-white/40 bg-white/10 px-4 text-white hover:bg-white/20 xl:px-5" aria-label="Member sign in">
                <x-icon name="card" />
                <span class="hidden xl:inline">Member sign in</span>
            </button>
        @endauth

        <form method="POST" action="{{ route('kiosk.reset') }}">
            @csrf
            <button type="submit" class="btn-icon border-white/40 bg-white/10 text-white hover:bg-white/20" aria-label="Exit kiosk and clear basket" title="Exit kiosk"><x-icon name="log-out" /></button>
        </form>
        </div>
    </header>

    <div class="flex min-h-0 flex-1">
        {{-- ================= Catalogue ================= --}}
        <main data-kiosk-region="catalogue" class="flex min-w-0 flex-1 flex-col">
            {{-- CATEGORIES — links like /kiosk?class=Allergy, one row that scrolls sideways --}}
            <nav class="border-b-2 border-line bg-surface/60" aria-label="Categories" x-data="horizontalScroller()">
                <div class="relative">
                    <button type="button" x-on:click="scrollBy(-1)" x-bind:disabled="atStart" class="btn-icon absolute top-1/2 left-2 z-10 size-11 -translate-y-1/2 border-line/80 bg-surface/95 shadow-sm transition-all duration-200 hover:-translate-x-0.5 disabled:pointer-events-none disabled:opacity-0" aria-label="Show previous categories">
                        <x-icon name="arrow-left" :size="20" />
                    </button>
                    <div x-ref="scroller" class="scroll-row flex gap-2 px-4 py-3 sm:px-6">
                        <a href="{{ $filterUrl(['class' => null]) }}" @if (blank($filters['class'] ?? null)) aria-current="true" @endif class="chip shrink-0">All categories</a>
                        @foreach ($classes as $class)
                            <a href="{{ $filterUrl(['class' => $class]) }}" @if (($filters['class'] ?? null) === $class) aria-current="true" @endif class="chip shrink-0">{{ $class }}</a>
                        @endforeach
                        <span class="w-8 shrink-0" aria-hidden="true"></span>
                    </div>
                    <button type="button" x-on:click="scrollBy(1)" x-bind:disabled="atEnd" class="btn-icon absolute top-1/2 right-2 z-10 size-11 -translate-y-1/2 border-line/80 bg-surface/95 shadow-sm transition-all duration-200 hover:translate-x-0.5 disabled:pointer-events-none disabled:opacity-0" aria-label="Show next categories">
                        <x-icon name="arrow-right" :size="20" />
                    </button>
                </div>
            </nav>

            {{-- relative: keeps screen-reader-only text inside this scroller, so it can't make the whole page scroll.
                 Scrolling tells the cards, so an open "What is it for?" closes again. --}}
            <div data-catalogue data-keep-scroll="catalogue" x-on:scroll.passive="$dispatch('catalogue-scroll', $el.scrollTop)" class="catalogue-shelf relative min-h-0 flex-1 overflow-y-auto px-4 py-7 sm:px-8 sm:py-9">
                {{-- Help is one tap away --}}
                <button type="button" x-on:click="panel = 'assistant'" class="focus-ring mb-5 flex w-full items-center gap-4 rounded-2xl border-2 border-assist/15 bg-assist-soft p-4 text-left transition-colors duration-150 hover:border-assist/40">
                    <span class="flex size-12 shrink-0 items-center justify-center rounded-xl bg-assist text-white"><x-icon name="sparkles" /></span>
                    <span class="min-w-0 flex-1">
                        <span class="block font-display text-lg font-bold text-assist">Out of stock, or not sure which one is the same?</span>
                        <span class="block text-ink-muted">Ask the assistant about other brands, doses, prices and what a medicine is for.</span>
                    </span>
                    <span class="btn btn-assist hidden md:inline-flex" aria-hidden="true">Ask now<x-icon name="arrow-right" /></span>
                </button>

                <div class="mb-4 flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
                    <div class="flex items-baseline gap-4">
                        <p class="font-display text-lg font-semibold text-ink-muted">{{ $medicines->count() }} {{ Str::plural('medicine', $medicines->count()) }}{{ filled($filters['q'] ?? null) ? " for “{$filters['q']}”" : '' }}</p>
                        @if (array_filter($filters))
                            <a href="{{ route('kiosk') }}" class="focus-ring rounded-lg px-1 font-semibold text-brand underline underline-offset-4 decoration-2 decoration-brand/30 hover:decoration-brand">Clear filters</a>
                        @endif
                    </div>

                    {{-- TYPE — /kiosk?type=otc --}}
                    <div class="flex rounded-full bg-sunken p-1" role="group" aria-label="Medicine type">
                        @foreach (['' => ['All', 'All types'], 'otc' => ['OTC', 'Over-the-counter'], 'prescription' => ['Prescription', 'Prescription']] as $value => [$short, $label])
                            <a
                                href="{{ $filterUrl(['type' => $value ?: null]) }}"
                                @if (($filters['type'] ?? '') === (string) $value) aria-current="true" @endif
                                aria-label="{{ $label }}"
                                class="focus-ring flex min-h-12 items-center rounded-full px-4 font-display text-base font-semibold whitespace-nowrap text-ink-muted transition-colors sm:px-5 aria-[current=true]:bg-surface aria-[current=true]:text-ink aria-[current=true]:shadow-sm"
                            ><span class="sm:hidden">{{ $short }}</span><span class="hidden sm:inline">{{ $label }}</span></a>
                        @endforeach
                    </div>
                </div>

                {{-- Columns follow the space the basket leaves, not the screen width, so cards never get too narrow for their buttons. --}}
                <ul class="grid grid-cols-[repeat(auto-fill,minmax(min(100%,19rem),1fr))] gap-7" aria-label="Medicines">
                    @forelse ($medicines as $medicine)
                        <x-medicine-card :medicine="$medicine" :in-basket="$basketQuantities[$medicine->id] ?? []" />
                    @empty
                        <li class="col-span-full mx-auto mt-16 max-w-md text-center">
                            <span class="mx-auto flex size-20 items-center justify-center rounded-2xl bg-sunken text-ink-muted"><x-icon name="search" :size="36" /></span>
                            <h2 class="mt-5 text-2xl font-bold">No medicines match</h2>
                            <p class="mt-2 text-lg text-ink-muted">Try the brand, product name or active ingredient. Our pharmacy team can also help at the counter.</p>
                            <a href="{{ route('kiosk') }}" class="btn btn-quiet mt-6">Show all medicines</a>
                        </li>
                    @endforelse
                </ul>

                <button
                    type="button"
                    x-show="showBackToTop"
                    x-transition.opacity.duration.200ms
                    x-on:click="$dispatch('catalogue-scroll-to-top')"
                    class="focus-ring fixed right-5 bottom-6 z-20 flex size-14 items-center justify-center rounded-full bg-brand text-white shadow-[0_14px_30px_-12px_rgb(15_118_110/0.75)] transition-transform duration-150 hover:-translate-y-1 hover:bg-brand-hover sm:right-8 lg:right-[27rem] xl:right-[29rem]"
                    aria-label="Back to top"
                    x-cloak
                >
                    <x-icon name="arrow-up" :size="22" />
                </button>
            </div>
        </main>

        {{-- ================= Basket (side panel; drawer below lg) ================= --}}
        <div x-show="panel === 'basket'" x-cloak x-on:click="panel = null" class="fixed inset-0 z-30 bg-ink/50 lg:hidden"></div>
        <aside data-kiosk-region="basket"
            class="fixed inset-y-0 right-0 z-40 flex w-full max-w-md translate-x-full flex-col border-l-2 border-line bg-surface transition-transform duration-300 ease-out-quart lg:static lg:w-[25rem] lg:max-w-none lg:translate-x-0 xl:w-[27rem]"
            x-bind:class="panel === 'basket' && 'translate-x-0!'"
            aria-labelledby="basket-title"
        >
            <div class="flex items-center justify-between gap-3 border-b-2 border-line px-5 py-4">
                <h2 id="basket-title" class="flex items-center gap-3 text-2xl font-bold"><x-icon name="basket" :size="26" />Your basket</h2>
                <span class="badge bg-sunken text-base text-ink">{{ $basketCount }} {{ Str::plural('item', $basketCount) }}</span>
                @if ($basketCount)
                    <form method="POST" action="{{ route('basket.clear') }}" x-data x-on:submit="confirm('Remove every medicine from your basket?') || $event.preventDefault()">
                        @csrf
                        <input type="hidden" name="panel" value="basket">
                        <button type="submit" class="btn-icon size-11 border-danger/30 text-danger transition-all duration-200 hover:-translate-y-0.5 hover:bg-danger-soft" aria-label="Clear entire basket">
                            <x-icon name="trash" :size="20" />
                        </button>
                    </form>
                @endif
                <button type="button" x-on:click="panel = null" class="btn-icon ml-auto lg:hidden" aria-label="Close basket"><x-icon name="x" /></button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-5">
                <ul class="space-y-3">
                    @forelse ($basket as $line)
                        @php $item = $line['medicine']; $packaging = $line['packaging']; @endphp
                        <li class="rounded-2xl border-2 border-line p-4">
                            <div class="flex gap-3">
                                <x-medicine-picture :medicine="$item" :show="$packaging->value" class="size-16 shrink-0 rounded-xl" />
                                <div class="min-w-0 flex-1">
                                    <p class="font-display text-lg leading-snug font-bold">{{ $item->name }}</p>
                                    <p class="text-ink-muted">{{ $item->drugLabel() }}</p>
                                    <p class="mt-1 flex flex-wrap items-center gap-x-2 text-sm">
                                        <span class="badge bg-brand-soft px-2 py-0.5 text-brand-hover">{{ $item->packagingName($packaging) }}</span>
                                        <span class="text-ink-muted">{{ $item->packDescription($packaging) }} · <x-money :amount="$line['unit_price']" /> each</span>
                                    </p>
                                </div>
                                <p class="font-display text-lg font-bold"><x-money :amount="$line['line_total']" /></p>
                            </div>
                            @if ($item->requiresPrescription())
                                <p class="mt-2 flex items-center gap-1.5 text-sm font-semibold text-rx">
                                    <x-icon name="shield-check" :size="16" />Prescription on file · checked at collection
                                </p>
                            @endif

                            <div class="mt-3 flex items-center gap-2">
                                {{-- UPDATE — each step is its own small form --}}
                                <form method="POST" action="{{ route('basket.update', $item) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="packaging" value="{{ $packaging->value }}">
                                    <input type="hidden" name="quantity" value="{{ $line['quantity'] - 1 }}">
                                    <input type="hidden" name="panel" value="basket">
                                    <button type="submit" class="btn-icon" @disabled($line['quantity'] <= 1) aria-label="One {{ Str::lower($item->packagingName($packaging)) }} fewer of {{ $item->name }}"><x-icon name="minus" /></button>
                                </form>
                                <span class="w-10 text-center font-display text-xl font-bold tabular-nums">{{ $line['quantity'] }}</span>
                                <form method="POST" action="{{ route('basket.update', $item) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="packaging" value="{{ $packaging->value }}">
                                    <input type="hidden" name="quantity" value="{{ $line['quantity'] + 1 }}">
                                    <input type="hidden" name="panel" value="basket">
                                    <button type="submit" class="btn-icon" @disabled($line['quantity'] >= $line['max_quantity']) aria-label="One more {{ Str::lower($item->packagingName($packaging)) }} of {{ $item->name }}"><x-icon name="plus" /></button>
                                </form>

                                {{-- DELETE --}}
                                <form method="POST" action="{{ route('basket.destroy', $item) }}" class="ml-auto">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="packaging" value="{{ $packaging->value }}">
                                    <input type="hidden" name="panel" value="basket">
                                    <button type="submit" class="focus-ring flex min-h-14 items-center gap-2 rounded-xl px-4 font-semibold text-danger hover:bg-danger-soft">
                                        <x-icon name="trash" :size="20" />Remove
                                    </button>
                                </form>
                            </div>
                        </li>
                    @empty
                        <li class="flex flex-col items-center py-16 text-center">
                            <span class="flex size-20 items-center justify-center rounded-2xl bg-sunken text-ink-muted"><x-icon name="basket" :size="36" /></span>
                            <p class="mt-5 font-display text-xl font-bold">Your basket is empty</p>
                            <p class="mt-1 text-ink-muted">Tap “Add to basket” on any medicine.</p>
                        </li>
                    @endforelse
                </ul>
            </div>

            <div class="border-t-2 border-line p-5">
                <div class="flex items-baseline justify-between">
                    <span class="font-display text-xl font-semibold">Total</span>
                    <span class="font-display text-3xl font-extrabold tabular-nums"><x-money :amount="$basketTotal" /></span>
                </div>
                <p class="mt-1 text-ink-muted">You pay at the counter. Nothing is charged here.</p>
                <button type="button" x-on:click="panel = 'checkout'" @disabled(! $basketCount) class="btn btn-primary mt-4 min-h-16 w-full text-xl">
                    Finish and get order slip<x-icon name="arrow-right" />
                </button>
            </div>
        </aside>
    </div>

    {{-- Basket bar below lg --}}
    <div data-kiosk-region="basket-bar" class="flex items-center gap-4 border-t-2 border-line bg-surface px-4 py-3 lg:hidden">
        <div class="min-w-0 flex-1">
            <p class="font-display text-xl font-extrabold tabular-nums"><x-money :amount="$basketTotal" /></p>
            <p class="text-sm text-ink-muted">{{ $basketCount }} {{ Str::plural('item', $basketCount) }} · Pay at the counter</p>
        </div>
        <button type="button" x-on:click="panel = 'basket'" class="btn btn-primary"><x-icon name="basket" />View basket</button>
    </div>

    {{-- ================= Stock assistant (AI) ================= --}}
    <x-stock-assistant :conversation="$conversation" :out-of-stock="$outOfStock" :basket-quantities="$basketQuantities" />

    {{-- ================= Member sign in =================
         Members type the number on their loyalty card (a real kiosk would read the card).
         Anyone without one, or who changes their mind, goes back to shopping as a non-member. --}}
    <div x-show="panel === 'member'" x-cloak x-trap.noscroll="panel === 'member'" class="pointer-events-none fixed inset-0 z-[60] flex items-start justify-center p-5 pt-10 sm:items-center sm:pt-5" role="dialog" aria-modal="true" aria-labelledby="member-title">
        <div x-on:click="panel = null" class="pointer-events-auto absolute inset-0 bg-ink/60"></div>
        <div class="pointer-events-auto relative w-full max-w-lg rounded-2xl bg-surface p-8">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h2 id="member-title" class="text-2xl font-bold">Sign in with your member card</h2>
                    <p class="mt-1 text-lg text-ink-muted">Members can add refills of prescriptions they have filled with us.</p>
                </div>
                <button type="button" x-on:click="panel = null" class="btn-icon" aria-label="Close" autofocus><x-icon name="x" /></button>
            </div>

            <form method="POST" action="{{ route('member.store') }}" class="mt-6">
                @csrf
                <input type="hidden" name="add" x-bind:value="refillFor ?? ''">
                <label for="member-number" class="font-display text-lg font-semibold">Member card number</label>
                <div class="mt-2 flex gap-2">
                    <input
                        id="member-number"
                        type="text"
                        name="member_number"
                        value="{{ old('member_number') }}"
                        maxlength="20"
                        inputmode="none"
                        autocomplete="off"
                        x-on:pointerdown="$event.pointerType === 'touch' && $dispatch('keyboard-show', 'member-number')"
                        @error('member_number') aria-invalid="true" aria-describedby="member-error" @enderror
                        class="focus-ring h-14 min-w-0 flex-1 rounded-xl border-2 border-line bg-canvas px-5 font-mono text-xl tracking-widest focus:border-brand focus:bg-surface aria-[invalid=true]:border-danger"
                    >
                    <button type="button" x-on:click="$dispatch('keyboard-toggle', 'member-number')" class="btn-icon" aria-label="On-screen keyboard"><x-icon name="keyboard" /></button>
                    <button type="submit" class="btn btn-primary">Sign in</button>
                </div>
                @error('member_number')
                    <p id="member-error" class="mt-2 font-semibold text-danger">{{ $message }}</p>
                @enderror
            </form>

            <div class="mt-6 border-t-2 border-line pt-6">
                <p class="font-display text-lg font-semibold">Not a member?</p>
                <p class="mt-1 text-ink-muted">You can keep shopping without a card. For prescription medicines, the pharmacist can help at the counter.</p>
                <button type="button" x-on:click="refillFor = null; panel = null" class="btn btn-quiet mt-4 w-full">Continue without a card</button>
            </div>
        </div>
    </div>

    {{-- ================= Checkout confirmation ================= --}}
    <div x-show="panel === 'checkout'" x-cloak x-trap.noscroll="panel === 'checkout'" class="pointer-events-none fixed inset-0 z-[60] flex items-center justify-center p-5" role="dialog" aria-modal="true" aria-labelledby="checkout-title">
        <div x-on:click="panel = null" class="pointer-events-auto absolute inset-0 bg-ink/60"></div>
        <div class="pointer-events-auto relative flex max-h-full w-full max-w-lg flex-col rounded-2xl bg-surface p-8">
            <h2 id="checkout-title" class="text-3xl font-bold">Ready to finish?</h2>
            <p class="mt-2 text-lg text-ink-muted">We'll give you an order slip with a barcode. Take it to the cashier to pay and collect your items.</p>

            <ul class="mt-6 min-h-0 divide-y-2 divide-line overflow-y-auto rounded-2xl border-2 border-line">
                @foreach ($basket as $line)
                    <li class="flex justify-between gap-4 px-4 py-3 text-lg">
                        <span>
                        <span class="font-semibold">{{ $line['medicine']->countOf($line['packaging'], $line['quantity']) }}</span> {{ $line['medicine']->name }}
                            <span class="block text-base text-ink-muted">{{ $line['medicine']->packDescription($line['packaging']) }}</span>
                        </span>
                        <span class="font-semibold tabular-nums"><x-money :amount="$line['line_total']" /></span>
                    </li>
                @endforeach
            </ul>

            <div class="mt-4 flex items-baseline justify-between">
                <span class="font-display text-xl font-semibold">Total to pay at the counter</span>
                <span class="font-display text-3xl font-extrabold tabular-nums"><x-money :amount="$basketTotal" /></span>
            </div>
            @if ($basket->contains(fn ($line) => $line['medicine']->requiresPrescription()))
                <p class="mt-3 flex gap-2 rounded-2xl bg-rx-soft p-3 text-rx">
                    <x-icon name="shield-check" class="mt-0.5" />The pharmacist checks prescription items when you collect them.
                </p>
            @endif

            <div class="mt-8 flex flex-col gap-3 sm:flex-row-reverse">
                {{-- STORE — creates the order and wipes the session --}}
                <form method="POST" action="{{ route('checkout.store') }}" class="flex-1">
                    @csrf
                    <button type="submit" class="btn btn-primary w-full" autofocus>Place order</button>
                </form>
                <button type="button" x-on:click="panel = null" class="btn btn-quiet flex-1">Keep shopping</button>
            </div>
        </div>
    </div>

    {{-- ================= Prescription required (flashed by BasketController) =================
         A swapped region, so the popup also appears after a basket form sent by app.js. --}}
    <div data-kiosk-region="prescription-notice">
    @if ($rx)
        <div x-data="{ open: true }" x-show="open" x-trap.noscroll="open" class="pointer-events-none fixed inset-0 z-[60] flex items-center justify-center p-5" role="alertdialog" aria-modal="true" aria-labelledby="rx-message" aria-describedby="rx-medicine">
            <div x-on:click="open = false" class="pointer-events-auto absolute inset-0 bg-ink/60"></div>
            <div class="pointer-events-auto relative w-full max-w-lg rounded-2xl bg-surface p-8 text-center">
                <span class="mx-auto flex size-20 items-center justify-center rounded-full bg-rx-soft text-rx"><x-icon name="shield-alert" :size="40" /></span>
                <h2 id="rx-message" class="mt-5 text-2xl leading-snug font-bold">{{ $rx['message'] }}</h2>
                <p id="rx-medicine" class="mt-2 text-lg font-semibold text-rx">{{ $rx['name'] }} · {{ $rx['drug'] }}</p>
                @auth
                    <p class="mt-4 text-lg text-ink-muted">The pharmacist can check your prescription and help you there.</p>
                @else
                    <p class="mt-4 text-lg text-ink-muted">Have you filled this prescription with us before? Sign in with your member card to add a refill.</p>
                @endauth
                <div class="mt-8 flex flex-col gap-3 sm:flex-row-reverse">
                    <button type="button" x-on:click="open = false" class="btn btn-primary flex-1" autofocus>OK</button>
                    @guest
                        <button type="button" x-on:click="open = false; refillFor = @js($rx['medicine_id']); panel = 'member'" class="btn btn-quiet flex-1"><x-icon name="card" />Member sign in</button>
                    @endguest
                </div>
            </div>
        </div>
    @endif
    </div>

    {{-- ================= Still there? ================= --}}
    <x-idle-timer>
        For your privacy, we'll clear this basket in <strong class="text-ink tabular-nums" x-text="`${remaining} seconds`"></strong>.
    </x-idle-timer>

    {{-- ================= On-screen keyboard ================= --}}
    <x-on-screen-keyboard />

    {{-- ================= Flash messages (like the notes app's x-alert) =================
         The status box stays put so screen readers announce what changes inside it;
         the swapped region inside it carries the messages from a basket form. Longer
         messages stay up longer, so there is time to read them. --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-24 z-[90] flex justify-center px-4 lg:bottom-8" role="status">
        <div data-kiosk-region="toasts" class="contents">
            @session('success')
                <p x-data="{ show: true }" x-init="setTimeout(() => show = false, {{ max(3500, mb_strlen($value) * 70) }})" x-show="show" x-transition.opacity.duration.200ms
                   class="flex max-w-xl items-center gap-3 rounded-2xl bg-ink px-5 py-4 text-lg font-semibold text-pretty text-white shadow-lg">
                    <x-icon name="check" />{{ $value }}
                </p>
            @endsession
            @if ($errors->any() && ! $errors->hasAny(['member_number', 'question']))
                <p x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show" x-transition.opacity.duration.200ms
                   class="flex max-w-xl items-center gap-3 rounded-2xl bg-danger px-5 py-4 text-lg font-semibold text-pretty text-white shadow-lg">
                    <x-icon name="info" />{{ $errors->first() }}
                </p>
            @endif
        </div>
    </div>
</div>
</x-layouts.kiosk>
