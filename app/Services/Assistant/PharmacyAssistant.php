<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Enums\Packaging;
use App\Models\Medicine;
use App\Support\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The kiosk's stock assistant. When a medicine is out of stock it suggests
 * in-stock brands with exactly the same active ingredient, and it answers
 * questions about those medicines: "are they the same dosage?", "which is
 * cheaper?", "do they need a prescription?", "what is it for?".
 *
 * The scope is enforced in code, not left to the model:
 *
 *  1. The database decides which medicines the customer means. A follow-up
 *     like "are they the same dosage?" is about the medicines the last
 *     reply was about. Questions asking what to take, how much, or whether
 *     it is safe get a fixed reply, and so does anything that names no
 *     medicine. The model never sees the customer's text.
 *  2. The database picks the candidates (Medicine::alternativesTo, same
 *     active ingredient, different brand, in stock). The model only ranks
 *     them and writes the message, and the JSON schema limits its picks to
 *     those candidate ids.
 *  3. The reply is checked before it is shown. A message that names any
 *     other medicine in the catalogue is thrown away and the template runs.
 *  4. Answers to questions (FollowUp) are written from catalogue facts
 *     only, never by the model.
 *
 * With no API key, steps 1, 2 and 4 run unchanged and a template writes the
 * suggestion.
 */
class PharmacyAssistant
{
    public const OUT_OF_SCOPE = 'I can help with the medicines in our catalogue: finding another brand when one is out of stock, and telling you how they compare, what they cost and what they are for. For advice on symptoms or which medicine to take, please speak with our pharmacist.';

    public const WHICH_MEDICINE = 'Which medicine do you mean? Type its name, for example “Is Allerta the same dosage as Claritin?”, or tap Find alternatives on a medicine that is out of stock.';

    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are the stock assistant on a pharmacy self-service kiosk.

        Your only job: a customer wanted a medicine that is out of stock, and you recommend in-stock alternatives that contain exactly the same active ingredient under a different brand. The kiosk has already looked these up in the pharmacy database and gives them to you as `candidates`. Every candidate has the same active ingredient as the `requested` medicine.

        Rules:
        - Recommend only medicines from `candidates`, by their brand and name. Never name any other medicine, brand or active ingredient.
        - Put candidates with the same strength and dosage form as the requested medicine first. If a candidate's strength or form differs, say so plainly and suggest checking with the pharmacist before switching.
        - Don't give medical advice: no dosing, symptoms, side effects, interactions, or whether it suits the customer. The pharmacist handles those.
        - If a candidate is prescription-only, mention that it needs a prescription on file.
        - Write `message` for someone standing at a kiosk: two or three short sentences, plain language, no markdown.
        - `alternative_ids` lists the candidate ids you recommend, best match first.
        PROMPT;

    /** Words that say nothing about which medicine the customer means. */
    private const STOP_WORDS = [
        'alternative', 'alternatives', 'another', 'anything', 'available', 'banig', 'boxes', 'brand', 'brands',
        'caplet', 'caplets', 'capsule', 'capsules', 'cheaper', 'cheapest', 'compare', 'cost', 'could', 'difference',
        'different', 'does', 'dosage', 'dose', 'doses', 'else', 'find', 'gamot', 'generic', 'have', 'instead',
        'kapareho', 'looking', 'magkano', 'many', 'medicine', 'medicines', 'much', 'need', 'other', 'para',
        'pareho', 'parehas', 'please', 'prescription', 'price', 'prices', 'replace', 'replacement', 'reseta',
        'saan', 'same', 'similar', 'softgel', 'something', 'stock', 'strength', 'strip', 'strips', 'substitute',
        'tablet', 'tablets', 'that', 'them', 'there', 'these', 'they', 'this', 'those', 'used', 'what', 'when',
        'where', 'which', 'with', 'would', 'your',
    ];

    public function __construct(private ?Client $client = null) {}

    /**
     * Customer tapped "Find alternatives" on a product.
     */
    public function suggestFor(Medicine $medicine): AssistantReply
    {
        $medicine->loadMissing('category');

        if ($medicine->isInStock()) {
            return new AssistantReply(
                "Good news: {$medicine->fullName()} is in stock, at ".Money::format($medicine->strip_price).' a strip or '.Money::format($medicine->box_price).' a box. You can add it below.',
                requested: $medicine,
                alternatives: new Collection([$medicine]),
            );
        }

        $candidates = Medicine::with('category')
            ->alternativesTo($medicine)
            ->limit(config('kiosk.assistant.max_alternatives'))
            ->get();

        if ($candidates->isEmpty()) {
            return new AssistantReply(
                "{$medicine->name} is out of stock and we have no other brand of {$medicine->active_ingredient} on the shelf right now. Our pharmacist can check the stockroom or order it in for you.",
                requested: $medicine,
            );
        }

        $draft = $this->client ? $this->askClaude($medicine, $candidates) : null;

        if ($draft !== null && ! $this->namesOtherMedicines($draft['message'], $medicine, $candidates)) {
            return new AssistantReply(
                $draft['message'],
                requested: $medicine,
                alternatives: $this->pick($candidates, $draft['alternative_ids']),
                source: 'claude',
            );
        }

        return new AssistantReply(
            $this->templateMessage($medicine, $candidates),
            requested: $medicine,
            alternatives: $candidates,
        );
    }

    /**
     * Customer typed a question. The catalogue decides which medicines it is
     * about. A question on a topic ("same dosage?", "cheaper?", "what is it
     * for?") is answered from catalogue facts; one that only names a
     * medicine gets its alternatives when it is out of stock. A follow-up
     * such as "are they the same dosage?" names nothing, so it is about the
     * medicines the last reply was about: $context, the asked-about one first.
     *
     * @param  Collection<int, Medicine>  $context
     */
    public function answer(string $question, Collection $context = new Collection): AssistantReply
    {
        if (Topic::asksForAdvice($question)) {
            return new AssistantReply(self::OUT_OF_SCOPE);
        }

        $named = $this->resolve($question);
        $topic = Topic::in($question);

        if ($topic !== null) {
            return $this->answerAbout($topic, $named, $context);
        }

        if ($named->isEmpty()) {
            return new AssistantReply(self::OUT_OF_SCOPE);
        }

        return $this->suggestFor($named->first(fn (Medicine $medicine) => ! $medicine->isInStock()) ?? $named->first());
    }

    /**
     * Work out which medicine the question is about and what to compare it
     * with, then answer from the catalogue.
     *
     * @param  Collection<int, Medicine>  $named  medicines the question names
     * @param  Collection<int, Medicine>  $context  medicines the last reply was about
     */
    private function answerAbout(Topic $topic, Collection $named, Collection $context): AssistantReply
    {
        $limit = config('kiosk.assistant.max_alternatives');
        $suggested = new Collection;

        if ($named->count() > 1) {
            // "Is RiteMed Loratadine the same as Claritin?"
            $subject = $named->first(fn (Medicine $medicine) => ! $medicine->isInStock()) ?? $named->first();
            $others = $named->reject(fn (Medicine $medicine) => $medicine->is($subject))->take($limit)->values();
        } elseif ($named->count() === 1) {
            $subject = $named->first();
            $others = new Collection;

            if (! $subject->isInStock()) {
                // "Is there a brand with the same dose as Claritin?" Suggest them too.
                $suggested = $others = Medicine::with('category')->alternativesTo($subject)->limit($limit)->get();
            } elseif ($topic->compares() && $context->isNotEmpty() && ! $context->first()->is($subject)) {
                // "Is Allerta the same dosage?" or "Is Virlix the same?" right after Claritin's alternatives.
                [$subject, $others] = [$context->first(), new Collection([$subject])];
            }
        } elseif ($context->isNotEmpty()) {
            // "Are they the same dosage?"
            $subject = $context->first();
            $others = $context->slice(1)->values();
        } else {
            return new AssistantReply(self::WHICH_MEDICINE);
        }

        return new AssistantReply(
            FollowUp::answer($topic, $subject, $others),
            requested: $subject,
            alternatives: $suggested,
            about: (new Collection([$subject]))->concat($others),
        );
    }

    /**
     * Medicines the question names, best match first: by product name
     * ("RiteMed Loratadine"), brand ("Neozep") or active ingredient
     * ("ibuprofen"), allowing a small typo ("neozip"). Descriptions and
     * categories are not searched, so a symptom word like "pain" or
     * "allergy" matches nothing and gets the out-of-scope reply.
     *
     * @return Collection<int, Medicine>
     */
    private function resolve(string $question): Collection
    {
        $text = mb_strtolower($question);

        $terms = Str::of($text)->matchAll('/[\pL\pN-]{4,}/u')
            ->diff(self::STOP_WORDS)
            ->unique()
            ->values();

        if ($terms->isEmpty()) {
            return new Collection;
        }

        $catalogue = Medicine::with('category')->get();
        $productsPerBrand = $catalogue->countBy(fn (Medicine $medicine) => mb_strtolower($medicine->brand));

        $matches = $catalogue
            ->map(fn (Medicine $medicine) => ['medicine' => $medicine, 'score' => $this->matchScore($medicine, $text, $terms, $productsPerBrand[mb_strtolower($medicine->brand)])])
            ->filter(fn (array $match) => $match['score'] > 0);

        if ($matches->isEmpty()) {
            return new Collection;
        }

        // A product named outright beats weaker matches, so "RiteMed
        // Loratadine" doesn't also bring in every other RiteMed product.
        $best = $matches->max('score');
        $cutoff = $best >= 6 ? 6 : $best;

        return new Collection($matches
            ->filter(fn (array $match) => $match['score'] >= $cutoff)
            ->sortByDesc('score')
            ->pluck('medicine')
            ->all());
    }

    /**
     * How surely the question names this medicine. 8: its full product name.
     * 6: a brand only this product has. 4: a brand shared by several products
     * ("RiteMed"). 2: its active ingredient. 1: one ingredient of a
     * combination product. A close typo counts like the real word.
     *
     * @param  BaseCollection<int, string>  $terms
     */
    private function matchScore(Medicine $medicine, string $text, BaseCollection $terms, int $productsWithBrand): int
    {
        $brand = mb_strtolower($medicine->brand);

        if ($this->mentions($text, mb_strtolower($medicine->fullName()))) {
            return 8;
        }

        if ($this->mentions($text, $brand) || $terms->contains(fn (string $term) => $this->isTypoOf($term, $brand))) {
            return $productsWithBrand === 1 ? 6 : 4;
        }

        $ingredients = $medicine->ingredients();

        $namesIngredient = collect($ingredients)->contains(fn (string $ingredient) => $this->mentions($text, mb_strtolower($ingredient))
            || $terms->contains(fn (string $term) => $this->isTypoOf($term, mb_strtolower(Str::before($ingredient, ' ')))));

        return match (true) {
            ! $namesIngredient => 0,
            count($ingredients) === 1 => 2,
            default => 1,
        };
    }

    /**
     * Does the text contain this phrase as whole words?
     */
    private function mentions(string $text, string $phrase): bool
    {
        return preg_match('/(?<![\pL\pN])'.preg_quote($phrase, '/').'(?![\pL\pN])/u', $text) === 1;
    }

    /**
     * Is the typed word the same as this name, or one or two letters off
     * ("neozip" for Neozep, "glucofage" for Glucophage)? The first letter
     * must match, and short words allow only one slip, so "allergy" is not
     * mistaken for Allerta.
     */
    private function isTypoOf(string $term, string $word): bool
    {
        $term = preg_replace('/[^\pL\pN]/u', '', $term);
        $word = preg_replace('/[^\pL\pN]/u', '', $word);

        if ($term === $word) {
            return true;
        }

        if (mb_strlen($term) < 5 || $word === '' || $term[0] !== $word[0]) {
            return false;
        }

        return levenshtein($term, $word) <= (mb_strlen($word) >= 9 ? 2 : 1);
    }

    /**
     * @param  Collection<int, Medicine>  $candidates
     * @return array{message: string, alternative_ids: list<int>}|null
     */
    private function askClaude(Medicine $requested, Collection $candidates): ?array
    {
        $describe = fn (Medicine $medicine) => [
            'brand' => $medicine->brand,
            'name' => $medicine->name,
            'active_ingredient' => $medicine->active_ingredient,
            'strength' => $medicine->strength,
            'dosage_form' => $medicine->dosage_form,
            'strip' => $medicine->packDescription(Packaging::Strip),
            'box' => $medicine->packDescription(Packaging::Box),
            'strip_price' => $medicine->strip_price,
            'box_price' => $medicine->box_price,
            'currency' => config('kiosk.currency'),
            'prescription_only' => $medicine->requiresPrescription(),
        ];

        try {
            $response = $this->client->beta->messages->create(
                model: config('kiosk.assistant.model'),
                maxTokens: 4096,
                system: self::SYSTEM_PROMPT,
                messages: [[
                    'role' => 'user',
                    'content' => json_encode([
                        'requested' => $describe($requested) + ['in_stock' => false],
                        'candidates' => $candidates->map(fn (Medicine $medicine) => ['id' => $medicine->id] + $describe($medicine))->all(),
                    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                ]],
                outputConfig: [
                    'effort' => 'low',
                    'format' => [
                        'type' => 'json_schema',
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'message' => ['type' => 'string'],
                                'alternative_ids' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'integer', 'enum' => $candidates->modelKeys()],
                                ],
                            ],
                            'required' => ['message', 'alternative_ids'],
                            'additionalProperties' => false,
                        ],
                    ],
                ],
                // If a safety classifier declines, the API retries on a fallback model.
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
        } catch (APIException $e) {
            Log::warning('Stock assistant request failed; using template.', ['error' => $e->getMessage()]);

            return null;
        }

        // refusal or max_tokens: no trustworthy JSON, so use the template.
        if ($response->stopReason !== 'end_turn') {
            Log::warning('Stock assistant stopped early; using template.', ['stop_reason' => $response->stopReason]);

            return null;
        }

        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $draft = json_decode($block->text, true);

                return is_string($draft['message'] ?? null) && is_array($draft['alternative_ids'] ?? null)
                    ? $draft
                    : null;
            }
        }

        return null;
    }

    /**
     * Keep the model's ranking, but only ids we offered. If it picked none,
     * fall back to the database order.
     *
     * @param  Collection<int, Medicine>  $candidates
     * @param  list<int>  $ids
     * @return Collection<int, Medicine>
     */
    private function pick(Collection $candidates, array $ids): Collection
    {
        $byId = $candidates->keyBy('id');

        $picked = collect($ids)->unique()->filter(fn ($id) => $byId->has($id))->map(fn ($id) => $byId[$id]);

        return $picked->isEmpty() ? $candidates : new Collection($picked->values()->all());
    }

    /**
     * Does the message mention a catalogue brand or ingredient that is not
     * the requested medicine or one of the candidates?
     *
     * @param  Collection<int, Medicine>  $candidates
     */
    private function namesOtherMedicines(string $message, Medicine $requested, Collection $candidates): bool
    {
        // Combination products are split into their ingredients, so naming
        // the paracetamol in Neozep Forte is not mistaken for naming Biogesic.
        $names = fn (Medicine $medicine) => [$medicine->brand, ...$medicine->ingredients()];

        $allowed = $candidates->toBase()->push($requested)
            ->flatMap($names)
            ->map(fn (string $word) => mb_strtolower($word))
            ->unique();

        return Medicine::query()
            ->select(['brand', 'active_ingredient'])
            ->get()
            ->flatMap($names)
            ->map(fn (string $word) => mb_strtolower($word))
            ->unique()
            ->diff($allowed)
            ->contains(fn (string $word) => preg_match('/\b'.preg_quote($word, '/').'\b/iu', $message) === 1);
    }

    /**
     * @param  Collection<int, Medicine>  $candidates
     */
    private function templateMessage(Medicine $requested, Collection $candidates): string
    {
        $best = $candidates->first();
        $ingredients = $requested->ingredients();
        $ingredientList = Arr::join(array_map(Str::lower(...), $ingredients), ', ', ' and ');

        $message = "{$requested->name} is out of stock, but {$best->fullName()} has the same active ".Str::plural('ingredient', count($ingredients)).", {$ingredientList}";

        $message .= $best->strength === $requested->strength && $best->dosage_form === $requested->dosage_form
            ? ", in the same {$requested->strength} ".Str::lower($requested->dosage_form).'.'
            : ". It comes as {$best->strength} ".Str::lower($best->dosage_form).', so check with the pharmacist before switching.';

        if ($best->requiresPrescription()) {
            $message .= ' It is prescription-only, so it needs a prescription on file.';
        }

        if ($candidates->count() > 1) {
            $message .= $candidates->count() === 2 ? ' Another option is below.' : ' More options are below.';
        }

        return $message;
    }
}
