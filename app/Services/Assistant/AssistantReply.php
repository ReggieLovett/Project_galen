<?php

namespace App\Services\Assistant;

use App\Models\Medicine;
use Illuminate\Database\Eloquent\Collection;

final readonly class AssistantReply
{
    /**
     * @param  Collection<int, Medicine>  $alternatives
     * @param  'claude'|'rules'  $source  who wrote the message
     * @param  string|null  $search  catalogue query the kiosk can offer instead
     */
    public function __construct(
        public string $message,
        public ?Medicine $requested = null,
        public Collection $alternatives = new Collection,
        public string $source = 'rules',
        public ?string $search = null,
    ) {}

    /**
     * The reply as the session stores it: ids only, so the drawer always
     * shows current prices and stock.
     *
     * @return array{message: string, source: string, requested_id: int|null, alternative_ids: list<int>, search: string|null}
     */
    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'source' => $this->source,
            'requested_id' => $this->requested?->id,
            'alternative_ids' => $this->alternatives->modelKeys(),
            'search' => $this->search,
        ];
    }
}
