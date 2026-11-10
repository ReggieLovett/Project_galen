<?php

namespace App\Services\Assistant;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Collection;

final readonly class AssistantReply
{
    /**
     * @param  Medicine|null  $requested  the medicine the customer asked about
     * @param  Collection<int, Medicine>  $alternatives  medicine cards shown under the message: in-stock
     *                                                   substitutes, or the medicine itself when it is in stock
     * @param  'claude'|'groq'|'rules'  $source  who wrote the message
     * @param  string|null  $search  catalogue query the kiosk can offer instead
     * @param  Collection<int, Medicine>  $about  the medicines the answer is about, the asked-about one
     *                                            first, so a follow-up like "are they the same dosage?"
     *                                            knows what "they" means. Defaults to requested + alternatives.
     */
    public function __construct(
        public string $message,
        public ?Medicine $requested = null,
        public Collection $alternatives = new Collection,
        public string $source = 'rules',
        public ?string $search = null,
        public Collection $about = new Collection,
    ) {}

    /**
     * The reply as the session stores it: ids only, so the drawer always
     * shows current prices and stock.
     *
     * @return array{message: string, source: string, requested_id: int|null, alternative_ids: list<int>, about_ids: list<int>, search: string|null}
     */
    public function toArray(): array
    {
        $about = $this->about->isNotEmpty()
            ? $this->about->modelKeys()
            : array_values(array_unique(array_filter([$this->requested?->id, ...$this->alternatives->modelKeys()])));

        return [
            'message' => $this->message,
            'source' => $this->source,
            'requested_id' => $this->requested?->id,
            'alternative_ids' => $this->alternatives->modelKeys(),
            'about_ids' => $about,
            'search' => $this->search,
        ];
    }
}
