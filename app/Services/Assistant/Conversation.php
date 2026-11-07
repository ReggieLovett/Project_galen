<?php

namespace App\Services\Assistant;

use App\Models\Medicine;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * The assistant chat for the current customer, kept in the session next to
 * the basket. It is wiped with the session when the kiosk resets.
 */
class Conversation
{
    private const KEY = 'kiosk.assistant';

    private const KEEP = 6;

    public function __construct(private Session $session) {}

    public function add(string $question, AssistantReply $reply): void
    {
        $entries = $this->session->get(self::KEY, []);

        $entries[] = ['question' => $question] + $reply->toArray();

        $this->session->put(self::KEY, array_slice($entries, -self::KEEP));
    }

    /**
     * Each question and reply, with its medicines loaded in one query.
     *
     * @return Collection<int, array{question: string, message: string, source: string, requested: Medicine|null, alternatives: Collection<int, Medicine>, about: Collection<int, Medicine>, search: string|null}>
     */
    public function entries(): Collection
    {
        $entries = collect($this->session->get(self::KEY, []));

        $medicines = Medicine::with('category')
            ->findMany($entries->flatMap(fn (array $entry) => [$entry['requested_id'], ...$entry['alternative_ids'], ...$this->aboutIds($entry)])->filter()->unique())
            ->keyBy('id');

        $load = fn (array $ids) => collect($ids)->map(fn (int $id) => $medicines->get($id))->filter()->values();

        return $entries->map(fn (array $entry) => [
            'question' => $entry['question'],
            'message' => $entry['message'],
            'source' => $entry['source'],
            'requested' => $entry['requested_id'] ? $medicines->get($entry['requested_id']) : null,
            'alternatives' => $load($entry['alternative_ids']),
            'about' => $load($this->aboutIds($entry)),
            'search' => $entry['search'],
        ]);
    }

    /**
     * What the latest reply about a medicine was about, the asked-about
     * medicine first. A follow-up like "are they the same dosage?" refers
     * to these. Replies about nothing (out-of-scope answers) are skipped.
     *
     * @return EloquentCollection<int, Medicine>
     */
    public function context(): EloquentCollection
    {
        $ids = collect($this->session->get(self::KEY, []))
            ->reverse()
            ->map(fn (array $entry) => $this->aboutIds($entry))
            ->first(fn (array $ids) => $ids !== []) ?? [];

        $medicines = Medicine::with('category')->findMany($ids)->keyBy('id');

        return new EloquentCollection(collect($ids)->map(fn (int $id) => $medicines->get($id))->filter()->values()->all());
    }

    /**
     * @param  array<string, mixed>  $entry
     * @return list<int>
     */
    private function aboutIds(array $entry): array
    {
        return $entry['about_ids'] ?? array_values(array_filter([$entry['requested_id'], ...$entry['alternative_ids']]));
    }
}
