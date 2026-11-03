<?php

namespace App\Services\Assistant;

use App\Models\Medicine;
use Illuminate\Contracts\Session\Session;
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
     * @return Collection<int, array{question: string, message: string, source: string, requested: Medicine|null, alternatives: Collection<int, Medicine>, search: string|null}>
     */
    public function entries(): Collection
    {
        $entries = collect($this->session->get(self::KEY, []));

        $medicines = Medicine::with('category')
            ->findMany($entries->flatMap(fn (array $entry) => [$entry['requested_id'], ...$entry['alternative_ids']])->filter()->unique())
            ->keyBy('id');

        return $entries->map(fn (array $entry) => [
            'question' => $entry['question'],
            'message' => $entry['message'],
            'source' => $entry['source'],
            'requested' => $entry['requested_id'] ? $medicines->get($entry['requested_id']) : null,
            'alternatives' => collect($entry['alternative_ids'])->map(fn (int $id) => $medicines->get($id))->filter()->values(),
            'search' => $entry['search'],
        ]);
    }
}
