@props(['conversation', 'outOfStock', 'basketQuantities' => []])

{{--
    The stock assistant drawer, opened by setting the kiosk's `panel` to
    "assistant". Every question is a plain form posting to assistant.store:
    typed ones, and the tap-to-ask suggestions, which are just questions
    with the words already filled in. A follow-up such as "Are they the
    same dosage?" is about the medicines in the reply above it.
--}}
@php
    // Shown once a reply is about some medicines.
    $followUps = [
        'Are they the same dosage?',
        'Which one is cheaper?',
        'Do they need a prescription?',
        'What is it for?',
        'How many are in a box?',
    ];

    $hasContext = $conversation->contains(fn (array $entry) => $entry['about']->isNotEmpty());

    // Before the first question: examples built from today's out-of-stock list.
    $examples = $outOfStock->take(3)->values()->map(fn ($medicine, int $index) => [
        "Is there another brand of {$medicine->name}?",
        "How much is {$medicine->name}?",
        "What is {$medicine->name} for?",
    ][$index]);
@endphp

<div x-show="panel === 'assistant'" x-cloak x-trap.noscroll="panel === 'assistant'" class="pointer-events-none fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="assistant-title">
    <div x-on:click="panel = null" class="pointer-events-auto absolute inset-0 bg-ink/50"></div>
    <section
        x-show="panel === 'assistant'"
        x-transition:enter="transition duration-300 ease-out-quart"
        x-transition:enter-start="translate-x-full"
        class="pointer-events-auto absolute inset-y-0 right-0 flex w-full max-w-2xl flex-col bg-surface"
    >
        <header class="flex items-center gap-4 bg-assist px-5 py-4 text-white">
            <span class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-white/15"><x-icon name="sparkles" /></span>
            <div class="min-w-0 flex-1">
                <h2 id="assistant-title" class="flex items-center gap-2 text-2xl font-bold">Stock assistant <span class="badge bg-white/15 text-white">AI</span></h2>
                <p class="text-white/80">Other brands, doses, prices and what a medicine is for</p>
            </div>
            <button type="button" x-on:click="panel = null" class="btn-icon border-white/40 bg-white/10 text-white hover:bg-white/20" aria-label="Close assistant" autofocus><x-icon name="x" /></button>
        </header>

        {{-- The chat. New replies are read out by screen readers. A swapped region, so its
             medicine cards show what is in the basket after one is added from here. --}}
        <div data-kiosk-region="assistant-log" data-keep-scroll="assistant-log" x-init="$nextTick(() => $el.scrollTop = $el.scrollHeight)" class="min-h-0 flex-1 space-y-5 overflow-y-auto p-5" role="log" aria-live="polite" aria-labelledby="assistant-title">
            <div class="flex gap-3 rounded-2xl bg-sunken p-4 text-lg leading-snug">
                <x-icon name="info" class="mt-0.5 text-assist" />
                <p>Ask me about any medicine on our shelves: another brand with <strong>the same active ingredient</strong>, whether the dose is the same, which is cheaper, or what it is for. I can't tell you what to take or how much. Our pharmacist can.</p>
            </div>

            @forelse ($conversation as $entry)
                <p class="ml-auto w-fit max-w-[85%] rounded-2xl rounded-br-md bg-ink px-5 py-3 text-lg text-white">{{ $entry['question'] }}</p>

                <div class="max-w-[95%] space-y-3">
                    <p class="w-fit rounded-2xl rounded-bl-md bg-assist-soft px-5 py-4 text-xl leading-relaxed text-pretty">{{ $entry['message'] }}</p>

                    @if ($entry['alternatives']->isNotEmpty())
                        <ul class="space-y-3">
                            @foreach ($entry['alternatives'] as $alternative)
                                <x-medicine-card :medicine="$alternative" :in-basket="$basketQuantities[$alternative->id] ?? []" panel="assistant" compact>
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

                    <p class="text-sm text-ink-muted">{{ match ($entry['source']) {
                        'claude' => 'Written by Claude from our live stock list',
                        'groq' => 'Written by AI from our live stock list',
                        default => 'From our live stock list',
                    } }}</p>
                </div>
            @empty
                @if ($outOfStock->isNotEmpty())
                    <div>
                        <p class="mb-3 font-display text-lg font-semibold">Out of stock today. Tap one to see other brands:</p>
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
                @endif

                @if ($examples->isNotEmpty())
                    <div>
                        <p class="mb-3 font-display text-lg font-semibold">Or try asking:</p>
                        <div class="flex flex-col items-start gap-2">
                            @foreach ($examples as $example)
                                <form method="POST" action="{{ route('assistant.store') }}">
                                    @csrf
                                    <input type="hidden" name="question" value="{{ $example }}">
                                    <button type="submit" class="chip border-assist/25 text-assist hover:border-assist"><x-icon name="send" :size="18" />{{ $example }}</button>
                                </form>
                            @endforeach
                        </div>
                    </div>
                @endif
            @endforelse
        </div>

        {{-- Tap-to-ask follow-ups about the medicines above --}}
        @if ($hasContext)
            <div class="border-t-2 border-line pt-3">
                <p class="px-4 font-display font-semibold text-ink-muted">Tap to ask about these medicines</p>
                <div class="scroll-row flex gap-2 px-4 pt-2 pb-3">
                    @foreach ($followUps as $followUp)
                        <form method="POST" action="{{ route('assistant.store') }}" class="shrink-0">
                            @csrf
                            <input type="hidden" name="question" value="{{ $followUp }}">
                            <button type="submit" class="chip border-assist/25 text-assist hover:border-assist">{{ $followUp }}</button>
                        </form>
                    @endforeach
                    <span class="w-8 shrink-0" aria-hidden="true"></span>
                </div>
            </div>
        @endif

        {{-- ASK — a typed question --}}
        <form method="POST" action="{{ route('assistant.store') }}" class="border-t-2 border-line p-4">
            @csrf
            <div class="flex items-center gap-2">
                <label for="assistant-input" class="sr-only">Ask about a medicine</label>
                <input
                    id="assistant-input"
                    type="text"
                    name="question"
                    maxlength="200"
                    inputmode="none"
                    autocomplete="off"
                    placeholder="e.g. Is Allerta the same dosage as Claritin?"
                    x-on:pointerdown="$event.pointerType === 'touch' && $dispatch('keyboard-show', 'assistant-input')"
                    class="focus-ring h-16 min-w-0 flex-1 rounded-xl border-2 border-line bg-canvas px-5 text-xl placeholder:text-ink-muted focus:border-assist focus:bg-surface"
                >
                <button type="button" x-on:click="$dispatch('keyboard-toggle', 'assistant-input')" class="btn-icon size-16" aria-label="On-screen keyboard"><x-icon name="keyboard" /></button>
                <button type="submit" class="btn btn-assist min-h-16 px-5" aria-label="Ask"><x-icon name="send" /><span class="hidden sm:inline">Ask</span></button>
            </div>
            @error('question')
                <p class="mt-2 font-semibold text-danger">{{ $message }}</p>
            @enderror
        </form>
    </section>
</div>
