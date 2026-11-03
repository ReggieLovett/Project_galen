<?php

namespace App\Services\Assistant;

use Anthropic\Client;
use Anthropic\Core\Exceptions\APIException;
use App\Models\Medicine;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The kiosk's stock assistant. It does one thing: when a medicine is out of
 * stock, it suggests in-stock brands with exactly the same active ingredient.
 *
 * The scope is enforced in code, not left to the model:
 *
 *  1. The database decides which medicine the customer means and whether it
 *     is out of stock. Anything else gets a fixed reply; the model is never
 *     called and never sees the customer's text.
 *  2. The database picks the candidates (Medicine::alternativesTo, same
 *     active ingredient, different brand, in stock). The model only ranks
 *     them and writes the message, and the JSON schema limits its picks to
 *     those candidate ids.
 *  3. The reply is checked before it is shown. A message that names any
 *     other medicine in the catalogue is thrown away and the template runs.
 *
 * With no API key, steps 1 and 2 run unchanged and a template writes the
 * message.
 */
class PharmacyAssistant
{
    public const OUT_OF_SCOPE = 'I can only help when a medicine is out of stock, by finding another brand with the same active ingredient. For advice on symptoms or which medicine to take, please speak with our pharmacist.';

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
        'alternative', 'alternatives', 'another', 'anything', 'available', 'brand', 'brands', 'capsule',
        'capsules', 'could', 'does', 'else', 'find', 'have', 'instead', 'looking', 'medicine', 'need',
        'other', 'please', 'replace', 'replacement', 'same', 'similar', 'something', 'stock', 'substitute',
        'tablet', 'tablets', 'that', 'there', 'this', 'what', 'when', 'where', 'which', 'with', 'would', 'your',
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
                "Good news: {$medicine->name} is in stock, so you can add it straight from the catalogue. I only look for alternatives when something is out of stock.",
                requested: $medicine,
                search: $medicine->brand,
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
     * Customer typed a question. Resolve it against the catalogue first;
     * only an out-of-stock match goes any further.
     */
    public function answer(string $question): AssistantReply
    {
        $matches = $this->resolve($question);

        if ($matches->isEmpty()) {
            return new AssistantReply(self::OUT_OF_SCOPE);
        }

        return $this->suggestFor($matches->first(fn (Medicine $medicine) => ! $medicine->isInStock()) ?? $matches->first());
    }

    /**
     * Medicines the question names by brand or active ingredient, best match
     * first. Product names are not searched, so a symptom word like "pain"
     * or "allergy" matches nothing and gets the out-of-scope reply.
     *
     * @return Collection<int, Medicine>
     */
    private function resolve(string $question): Collection
    {
        $terms = Str::of($question)->lower()->matchAll('/[\pL\pN-]{4,}/u')
            ->diff(self::STOP_WORDS)
            ->unique()
            ->take(6)
            ->values();

        if ($terms->isEmpty()) {
            return new Collection;
        }

        return Medicine::with('category')
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhereLike('brand', "%{$term}%")->orWhereLike('active_ingredient', "%{$term}%");
                }
            })
            ->get()
            ->sortByDesc(fn (Medicine $medicine) => $terms->sum(fn (string $term) => match (true) {
                Str::contains($medicine->brand, $term, ignoreCase: true) => 2,
                Str::contains($medicine->active_ingredient, $term, ignoreCase: true) => 1,
                default => 0,
            }))
            ->values();
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
            'pack_size' => $medicine->pack_size,
            'price' => $medicine->price,
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
        $allowed = $candidates->toBase()->push($requested)
            ->flatMap(fn (Medicine $medicine) => [$medicine->brand, $medicine->active_ingredient])
            ->map(fn (string $word) => mb_strtolower($word))
            ->unique();

        return Medicine::query()
            ->select(['brand', 'active_ingredient'])
            ->get()
            ->flatMap(fn (Medicine $medicine) => [$medicine->brand, $medicine->active_ingredient])
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
        $ingredient = Str::lower($requested->active_ingredient);

        $message = "{$requested->name} is out of stock, but {$best->fullName()} has the same active ingredient, {$ingredient}";

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
