@php
    // Filter links keep the other filters: route('kiosk', ['q' => 'advil', 'type' => 'otc'])
    $filterUrl = fn (array $changes) => route('kiosk', array_filter(array_merge($filters, $changes)));

    // Which drawer or popup to show after a redirect back here.
    $openPanel = session('panel')
        ?? ($errors->has('member_number') ? 'member' : ($errors->has('question') ? 'assistant' : null));

    $rx = session('prescription_required');
@endphp

<x-layouts.kiosk title="Shop" class="overflow-hidden">
<div x-data="{ panel: @js($openPanel) }" x-on:keydown.escape.window="panel = null" class="flex h-dvh flex-col">

    {{-- ================= Top bar ================= --}}
    <header class="flex flex-wrap items-center gap-3 border-b-2 border-line bg-surface px-4 py-3 sm:flex-nowrap sm:gap-4 sm:px-6 sm:py-4">
        <a href="{{ route('kiosk') }}" class="focus-ring flex shrink-0 items-center gap-3 rounded-xl" aria-label="{{ config('kiosk.name') }}, all medicines">
            <x-logo-mark class="size-12 rounded-xl bg-brand text-white" />
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
                class="focus-ring h-14 w-full rounded-xl border-2 border-line bg-canvas pr-36 pl-14 text-lg text-ellipsis placeholder:text-ink-muted focus:border-brand focus:bg-surface"
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
        <button type="button" x-on:click="panel = 'assistant'" class="btn btn-assist shrink-0 px-4 xl:px-6" aria-label="Open stock assistant">
            <x-icon name="sparkles" />
            <span class="hidden xl:inline">Stock assistant</span>
        </button>

        {{-- Shown only when a member card is signed in --}}
        @auth
            <div class="flex shrink-0 items-center gap-2">
                <span class="hidden items-center gap-2 rounded-xl bg-brand-soft px-4 py-3 font-display text-lg font-semibold text-brand-hover xl:flex">
                    <x-icon name="user" />Hi, {{ auth()->user()->firstName() }}
                </span>
                <form method="POST" action="{{ route('member.destroy') }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn-icon" aria-label="Sign out of member card"><x-icon name="log-out" /></button>
                </form>
            </div>
        @else
            <button type="button" x-on:click="panel = 'member'" class="btn btn-quiet shrink-0 px-4 xl:px-5" aria-label="Member sign in">
                <x-icon name="card" />
                <span class="hidden xl:inline">Member sign in</span>
            </button>
        @endauth

        <form method="POST" action="{{ route('kiosk.reset') }}">
            @csrf
            <button type="submit" class="btn-icon" aria-label="Start over"><x-icon name="rotate" /></button>
        </form>
        </div>
    </header>

    <div class="flex min-h-0 flex-1">
        {{-- ================= Catalogue ================= --}}
        <main class="flex min-w-0 flex-1 flex-col">
            {{-- CATEGORIES — links like /kiosk?class=Allergy, one row that scrolls sideways --}}
            <nav class="border-b-2 border-line bg-surface/60" aria-label="Categories">
                <div class="scroll-row flex gap-2 px-4 py-3 sm:px-6">
                    <a href="{{ $filterUrl(['class' => null]) }}" @if (blank($filters['class'] ?? null)) aria-current="true" @endif class="chip shrink-0">All categories</a>
                    @foreach ($classes as $class)
                        <a href="{{ $filterUrl(['class' => $class]) }}" @if (($filters['class'] ?? null) === $class) aria-current="true" @endif class="chip shrink-0">{{ $class }}</a>
                    @endforeach
                    <span class="w-8 shrink-0" aria-hidden="true"></span>
                </div>
            </nav>

            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-5 sm:px-6">
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

                <ul class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4" aria-label="Medicines">
                    @forelse ($medicines as $medicine)
                        <x-medicine-card :medicine="$medicine" :in-basket="$basketQuantities[$medicine->id] ?? 0" />
                    @empty
                        <li class="col-span-full mx-auto mt-16 max-w-md text-center">
                            <span class="mx-auto flex size-20 items-center justify-center rounded-2xl bg-sunken text-ink-muted"><x-icon name="search" :size="36" /></span>
                            <h2 class="mt-5 text-2xl font-bold">No medicines match</h2>
                            <p class="mt-2 text-lg text-ink-muted">Try the brand, product name or active ingredient. Our pharmacy team can also help at the counter.</p>
                            <a href="{{ route('kiosk') }}" class="btn btn-quiet mt-6">Show all medicines</a>
                        </li>
                    @endforelse
                </ul>
            </div>
        </main>

        {{-- ================= Basket (side panel; drawer below lg) ================= --}}
        <div x-show="panel === 'basket'" x-cloak x-on:click="panel = null" class="fixed inset-0 z-30 bg-ink/50 lg:hidden"></div>
        <aside
            class="fixed inset-y-0 right-0 z-40 flex w-full max-w-md translate-x-full flex-col border-l-2 border-line bg-surface transition-transform duration-300 ease-out-quart lg:static lg:w-[25rem] lg:max-w-none lg:translate-x-0 xl:w-[27rem]"
            x-bind:class="panel === 'basket' && 'translate-x-0!'"
            aria-labelledby="basket-title"
        >
            <div class="flex items-center justify-between gap-3 border-b-2 border-line px-5 py-4">
                <h2 id="basket-title" class="flex items-center gap-3 text-2xl font-bold"><x-icon name="basket" :size="26" />Your basket</h2>
                <span class="badge bg-sunken text-base text-ink">{{ $basketCount }} {{ Str::plural('item', $basketCount) }}</span>
                <button type="button" x-on:click="panel = null" class="btn-icon ml-auto lg:hidden" aria-label="Close basket"><x-icon name="x" /></button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto p-5">
                <ul class="space-y-3">
                    @forelse ($basket as $line)
                        @php $item = $line['medicine']; @endphp
                        <li class="rounded-2xl border-2 border-line p-4">
                            <div class="flex justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="font-display text-lg leading-snug font-bold">{{ $item->name }}</p>
                                    <p class="text-ink-muted">{{ $item->drugLabel() }}</p>
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
                                    <input type="hidden" name="quantity" value="{{ $line['quantity'] - 1 }}">
                                    <input type="hidden" name="panel" value="basket">
                                    <button type="submit" class="btn-icon" @disabled($line['quantity'] <= 1) aria-label="One fewer {{ $item->name }}"><x-icon name="minus" /></button>
                                </form>
                                <span class="w-10 text-center font-display text-xl font-bold tabular-nums">{{ $line['quantity'] }}</span>
                                <form method="POST" action="{{ route('basket.update', $item) }}">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="quantity" value="{{ $line['quantity'] + 1 }}">
                                    <input type="hidden" name="panel" value="basket">
                                    <button type="submit" class="btn-icon" @disabled($line['quantity'] >= $line['max_quantity']) aria-label="One more {{ $item->name }}"><x-icon name="plus" /></button>
                                </form>

                                {{-- DELETE --}}
                                <form method="POST" action="{{ route('basket.destroy', $item) }}" class="ml-auto">
                                    @csrf
                                    @method('DELETE')
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
    <div class="flex items-center gap-4 border-t-2 border-line bg-surface px-4 py-3 lg:hidden">
        <div class="min-w-0 flex-1">
            <p class="font-display text-lg font-bold">{{ $basketCount }} {{ Str::plural('item', $basketCount) }} · <x-money :amount="$basketTotal" /></p>
            <p class="text-sm text-ink-muted">Pay at the counter</p>
        </div>
        <button type="button" x-on:click="panel = 'basket'" class="btn btn-primary"><x-icon name="basket" />View basket</button>
    </div>

    {{-- ================= Stock assistant (AI) ================= --}}
    <div x-show="panel === 'assistant'" x-cloak x-trap.noscroll="panel === 'assistant'" class="pointer-events-none fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="assistant-title">
        <div x-on:click="panel = null" class="pointer-events-auto absolute inset-0 bg-ink/50"></div>
        <section
            x-show="panel === 'assistant'"
            x-transition:enter="transition duration-300 ease-out-quart"
            x-transition:enter-start="translate-x-full"
            class="pointer-events-auto absolute inset-y-0 right-0 flex w-full max-w-xl flex-col bg-surface"
        >
            <header class="flex items-center gap-4 border-b-2 border-line px-5 py-4">
                <span class="flex size-12 items-center justify-center rounded-2xl bg-assist-soft text-assist"><x-icon name="sparkles" /></span>
                <div class="min-w-0 flex-1">
                    <h2 id="assistant-title" class="flex items-center gap-2 text-2xl font-bold">Stock assistant <span class="badge bg-assist-soft text-assist">AI</span></h2>
                    <p class="text-ink-muted">Other brands, same active ingredient</p>
                </div>
                <button type="button" x-on:click="panel = null" class="btn-icon" aria-label="Close assistant" autofocus><x-icon name="x" /></button>
            </header>

            <div x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5">
                <div class="flex gap-3 rounded-2xl bg-sunken p-4">
                    <x-icon name="info" class="mt-0.5 text-assist" />
                    <p>When a medicine is out of stock, I look for another brand with <strong>exactly the same active ingredient</strong>. I can't give medical advice. For that, please speak with our pharmacist.</p>
                </div>

                @forelse ($conversation as $entry)
                    <p class="ml-auto w-fit max-w-[85%] rounded-2xl rounded-br-md bg-ink px-5 py-3 text-lg text-white">{{ $entry['question'] }}</p>

                    <div class="max-w-[95%] space-y-3">
                        <p class="w-fit rounded-2xl rounded-bl-md bg-assist-soft px-5 py-3 text-lg">{{ $entry['message'] }}</p>

                        @if ($entry['alternatives']->isNotEmpty())
                            <ul class="space-y-3">
                                @foreach ($entry['alternatives'] as $alternative)
                                    <x-medicine-card :medicine="$alternative" :in-basket="$basketQuantities[$alternative->id] ?? 0" panel="assistant" compact>
                                        @if ($entry['requested'] && ($alternative->strength !== $entry['requested']->strength || $alternative->dosage_form !== $entry['requested']->dosage_form))
                                            <p class="mt-3 flex items-center gap-1.5 text-sm font-semibold text-rx">
                                                <x-icon name="info" :size="16" />Different strength or form. Check with the pharmacist.
                                            </p>
                                        @endif
                                    </x-medicine-card>
                                @endforeach
                            </ul>
                        @endif

                        @if ($entry['search'])
                            <a href="{{ route('kiosk', ['q' => $entry['search']]) }}" class="btn btn-quiet">
                                <x-icon name="search" />Show {{ $entry['search'] }} in the catalogue
                            </a>
                        @endif

                        <p class="text-sm text-ink-muted">{{ $entry['source'] === 'claude' ? 'Written by Claude from our live stock list' : 'From our live stock list' }}</p>
                    </div>
                @empty
                    <div class="pt-2">
                        <p class="mb-3 font-display text-lg font-semibold">Out of stock today</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($outOfStock as $medicine)
                                <form method="POST" action="{{ route('assistant.store') }}">
                                    @csrf
                                    <input type="hidden" name="medicine_id" value="{{ $medicine->id }}">
                                    <button type="submit" class="chip">{{ $medicine->name }}</button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endforelse
            </div>

            {{-- ASK — a typed question --}}
            <form method="POST" action="{{ route('assistant.store') }}" class="border-t-2 border-line p-4">
                @csrf
                <div class="flex items-center gap-2">
                    <label for="assistant-input" class="sr-only">Which medicine is out of stock?</label>
                    <input
                        id="assistant-input"
                        type="text"
                        name="question"
                        maxlength="200"
                        inputmode="none"
                        autocomplete="off"
                        placeholder="e.g. Is there another brand of Claritin?"
                        x-on:pointerdown="$event.pointerType === 'touch' && $dispatch('keyboard-show', 'assistant-input')"
                        class="focus-ring h-14 min-w-0 flex-1 rounded-xl border-2 border-line bg-canvas px-5 text-lg placeholder:text-ink-muted focus:border-assist focus:bg-surface"
                    >
                    <button type="button" x-on:click="$dispatch('keyboard-toggle', 'assistant-input')" class="btn-icon" aria-label="On-screen keyboard"><x-icon name="keyboard" /></button>
                    <button type="submit" class="btn btn-assist px-5" aria-label="Send"><x-icon name="send" /></button>
                </div>
                @error('question')
                    <p class="mt-2 font-semibold text-danger">{{ $message }}</p>
                @enderror
            </form>
        </section>
    </div>

    {{-- ================= Member sign in (mock card tap) ================= --}}
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

            <p class="mt-6 font-display text-lg font-semibold">Tap a demo card</p>
            <div class="mt-2 grid gap-3">
                @foreach ($demoMembers as $member)
                    <form method="POST" action="{{ route('member.store') }}">
                        @csrf
                        <input type="hidden" name="member_number" value="{{ $member->member_number }}">
                        @if ($rx) <input type="hidden" name="add" value="{{ $rx['medicine_id'] }}"> @endif
                        <button type="submit" class="focus-ring flex min-h-16 w-full items-center gap-4 rounded-xl bg-brand px-5 text-left text-white transition-[background-color,transform] duration-150 hover:bg-brand-hover active:scale-[0.98]">
                            <x-icon name="card" :size="28" />
                            <span class="flex-1">
                                <span class="block font-display text-lg font-bold">{{ $member->name }}</span>
                                <span class="block font-mono tracking-widest">•••• {{ substr($member->member_number, -4) }}</span>
                            </span>
                            <x-icon name="arrow-right" />
                        </button>
                    </form>
                @endforeach
            </div>

            <form method="POST" action="{{ route('member.store') }}" class="mt-6">
                @csrf
                @if ($rx) <input type="hidden" name="add" value="{{ $rx['medicine_id'] }}"> @endif
                <label for="member-number" class="font-display text-lg font-semibold">Or enter your member number</label>
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
                        <span><span class="font-semibold tabular-nums">{{ $line['quantity'] }} ×</span> {{ $line['medicine']->name }}</span>
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

    {{-- ================= Prescription required (flashed by BasketController) ================= --}}
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
                        <button type="button" x-on:click="open = false; panel = 'member'" class="btn btn-quiet flex-1"><x-icon name="card" />Member sign in</button>
                    @endguest
                </div>
            </div>
        </div>
    @endif

    {{-- ================= Still there? ================= --}}
    <div x-data="idleTimer({ timeout: {{ config('kiosk.idle_timeout') }}, countdown: {{ config('kiosk.idle_countdown') }} })">
        <form x-ref="reset" method="POST" action="{{ route('kiosk.reset') }}" class="hidden">@csrf</form>
        <div x-show="open" x-cloak x-trap.noscroll="open" class="fixed inset-0 z-[80] flex items-center justify-center p-5" role="alertdialog" aria-modal="true" aria-labelledby="idle-title" aria-describedby="idle-message">
            <div class="absolute inset-0 bg-ink/70"></div>
            <div class="relative w-full max-w-md rounded-2xl bg-surface p-8 text-center">
                <span class="mx-auto flex size-20 items-center justify-center rounded-full bg-sunken text-ink"><x-icon name="clock" :size="40" /></span>
                <h2 id="idle-title" class="mt-5 text-3xl font-bold">Are you still there?</h2>
                <p id="idle-message" class="mt-3 text-lg text-ink-muted">
                    For your privacy, we'll clear this basket in <strong class="text-ink tabular-nums" x-text="`${remaining} seconds`"></strong>.
                </p>
                <div class="mt-8 flex flex-col gap-3">
                    <button type="button" x-on:click="stillHere()" class="btn btn-primary" autofocus>I'm still here</button>
                    <button type="button" x-on:click="$refs.reset.submit()" class="btn btn-quiet">Start over</button>
                </div>
            </div>
        </div>
    </div>

    {{-- ================= On-screen keyboard ================= --}}
    <div
        x-data="keyboard"
        x-on:keyboard-toggle.window="toggle($event.detail)"
        x-on:keyboard-show.window="show($event.detail)"
        x-show="open"
        x-cloak
        x-transition:enter="transition duration-200 ease-out-quart"
        x-transition:enter-start="translate-y-full"
        class="fixed inset-x-0 bottom-0 z-[70] border-t-2 border-line-strong bg-sunken px-3 pt-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]"
        role="group"
        aria-label="On-screen keyboard"
    >
        <div class="mx-auto max-w-5xl space-y-2">
            @foreach (['1234567890', 'qwertyuiop', 'asdfghjkl', 'zxcvbnm'] as $row)
                <div class="flex justify-center gap-2 {{ $loop->iteration === 3 ? 'px-[5%]' : '' }}">
                    @foreach (str_split($row) as $key)
                        <button type="button" x-on:mousedown.prevent x-on:click="press('{{ $key }}')" class="key" tabindex="-1">{{ $key }}</button>
                    @endforeach
                    @if ($loop->last)
                        <button type="button" x-on:mousedown.prevent x-on:click="press('backspace')" class="key max-w-36 flex-[1.6]" tabindex="-1" aria-label="Backspace"><x-icon name="delete" /></button>
                    @endif
                </div>
            @endforeach
            <div class="flex gap-2">
                <button type="button" x-on:mousedown.prevent x-on:click="press('clear')" class="key max-w-40 text-base normal-case" tabindex="-1">Clear</button>
                <button type="button" x-on:mousedown.prevent x-on:click="press('space')" class="key flex-[5] text-base normal-case" tabindex="-1">Space</button>
                <button type="button" x-on:mousedown.prevent x-on:click="press('enter')" class="key max-w-48 flex-[1.5] bg-brand! text-base text-white! normal-case shadow-[0_2px_0_var(--color-brand-hover)]!" tabindex="-1">Go</button>
            </div>
        </div>
    </div>

    {{-- ================= Flash messages (like the notes app's x-alert) ================= --}}
    <div class="pointer-events-none fixed inset-x-0 bottom-24 z-[90] flex justify-center px-4 lg:bottom-8" role="status">
        @session('success')
            <p x-data="{ show: true }" x-init="setTimeout(() => show = false, 3500)" x-show="show" x-transition.opacity.duration.200ms
               class="flex max-w-xl items-center gap-3 rounded-2xl bg-ink px-5 py-4 text-lg font-semibold text-white shadow-lg">
                <x-icon name="check" />{{ $value }}
            </p>
        @endsession
        @if ($errors->any() && ! $errors->hasAny(['member_number', 'question']))
            <p x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show" x-transition.opacity.duration.200ms
               class="flex max-w-xl items-center gap-3 rounded-2xl bg-danger px-5 py-4 text-lg font-semibold text-white shadow-lg">
                <x-icon name="info" />{{ $errors->first() }}
            </p>
        @endif
    </div>
</div>
</x-layouts.kiosk>
