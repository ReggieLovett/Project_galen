<?php

namespace App\Services;

use App\Enums\Packaging;
use App\Models\Medicine;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The kiosk basket, kept in the session as
 * [medicine id => [packaging => quantity]], e.g. [7 => ['strip' => 2, 'box' => 1]].
 *
 * A medicine can be in the basket by the strip and by the box at the same
 * time. Both draw on the same shelf stock, which is counted in strips.
 */
class Cart
{
    private const KEY = 'kiosk.cart';

    public function __construct(private Session $session) {}

    /**
     * @return array<int, array<string, int>>
     */
    public function quantities(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function isEmpty(): bool
    {
        return $this->quantities() === [];
    }

    public function has(Medicine $medicine, Packaging $packaging): bool
    {
        return $this->quantityOf($medicine, $packaging) > 0;
    }

    public function quantityOf(Medicine $medicine, Packaging $packaging): int
    {
        return $this->quantities()[$medicine->id][$packaging->value] ?? 0;
    }

    /**
     * How many strips of this medicine the basket would take off the shelf.
     */
    public function stripsOf(Medicine $medicine): int
    {
        return collect($this->quantities()[$medicine->id] ?? [])
            ->sum(fn (int $quantity, string $packaging) => $quantity * $medicine->stripsPer(Packaging::from($packaging)));
    }

    public function add(Medicine $medicine, Packaging $packaging, int $quantity = 1): void
    {
        $this->put($medicine, $packaging, $this->quantityOf($medicine, $packaging) + $quantity);
    }

    /**
     * Add strips and boxes of one medicine in one tap. Either all of it fits
     * the shelf stock and the per-item limit, or nothing is added.
     *
     * @param  array<string, int>  $quantities  packaging value => how many to add, e.g. ['strip' => 2, 'box' => 1]
     *
     * @throws ValidationException
     */
    public function addMany(Medicine $medicine, array $quantities): void
    {
        $before = $this->quantities();

        try {
            foreach (Packaging::cases() as $packaging) {
                if (($quantities[$packaging->value] ?? 0) > 0) {
                    $this->add($medicine, $packaging, $quantities[$packaging->value]);
                }
            }
        } catch (ValidationException $e) {
            $this->session->put(self::KEY, $before);

            throw $e;
        }
    }

    /**
     * Set how many strips or boxes of a medicine the customer wants, capped
     * by shelf stock and the per-item limit.
     *
     * @throws ValidationException
     */
    public function put(Medicine $medicine, Packaging $packaging, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($medicine, $packaging);

            return;
        }

        if (! $medicine->isInStock()) {
            throw ValidationException::withMessages([
                'medicine_id' => "{$medicine->name} is out of stock. Ask the assistant for an alternative brand.",
            ]);
        }

        $onShelf = $this->onShelfFor($medicine, $packaging);
        $limit = min($onShelf, config('kiosk.max_quantity_per_item'));

        if ($quantity > $limit) {
            throw ValidationException::withMessages([
                'quantity' => match (true) {
                    $onShelf === 0 && $packaging === Packaging::Box => "There isn't a full box of {$medicine->name} left. You can buy it by the strip.",
                    $onShelf === 0 => "Your basket already has all the {$medicine->name} we have in stock.",
                    $limit === $onShelf => "Only {$packaging->count($limit)} of {$medicine->name} left in stock.",
                    default => "You can buy up to {$packaging->count($limit)} of {$medicine->name} per visit.",
                },
            ]);
        }

        $this->session->put(self::KEY.".{$medicine->id}.{$packaging->value}", $quantity);
    }

    /**
     * The most strips or boxes of this medicine the customer may have.
     */
    public function maxQuantity(Medicine $medicine, Packaging $packaging): int
    {
        return min($this->onShelfFor($medicine, $packaging), config('kiosk.max_quantity_per_item'));
    }

    /**
     * Remove one packaging of a medicine, or the medicine altogether.
     */
    public function remove(Medicine $medicine, ?Packaging $packaging = null): void
    {
        if ($packaging === null) {
            $this->session->forget(self::KEY.".{$medicine->id}");

            return;
        }

        $this->session->forget(self::KEY.".{$medicine->id}.{$packaging->value}");

        if (empty($this->session->get(self::KEY.".{$medicine->id}"))) {
            $this->session->forget(self::KEY.".{$medicine->id}");
        }
    }

    /**
     * Drop items the given customer may not buy, e.g. a member's refill
     * after they sign out.
     *
     * @return Collection<int, Medicine> the removed medicines
     */
    public function removeItemsNotAllowedFor(?User $customer): Collection
    {
        $removed = $this->medicines()->filter(
            fn (Medicine $medicine) => Gate::forUser($customer)->denies('addToBasket', $medicine)
        );

        $removed->each(fn (Medicine $medicine) => $this->remove($medicine));

        return $removed->values()->toBase();
    }

    public function clear(): void
    {
        $this->session->forget(self::KEY);
    }

    /**
     * @return EloquentCollection<int, Medicine>
     */
    public function medicines(): EloquentCollection
    {
        return Medicine::with('category')->findMany(array_keys($this->quantities()));
    }

    /**
     * The basket's lines, sorted by name (strip before box), with what each
     * one costs.
     *
     * @return Collection<int, array{medicine: Medicine, packaging: Packaging, quantity: int, unit_price: string, line_total: float, max_quantity: int}>
     */
    public function lines(): Collection
    {
        $quantities = $this->quantities();

        return $this->medicines()
            ->sortBy('name')
            ->toBase()
            ->flatMap(fn (Medicine $medicine) => collect(Packaging::cases())
                ->filter(fn (Packaging $packaging) => isset($quantities[$medicine->id][$packaging->value]))
                ->map(fn (Packaging $packaging) => [
                    'medicine' => $medicine,
                    'packaging' => $packaging,
                    'quantity' => $quantities[$medicine->id][$packaging->value],
                    'unit_price' => $medicine->priceOf($packaging),
                    'line_total' => round($medicine->priceOf($packaging) * $quantities[$medicine->id][$packaging->value], 2),
                    'max_quantity' => $this->maxQuantity($medicine, $packaging),
                ]))
            ->values();
    }

    /**
     * How many strips and boxes are in the basket.
     */
    public function count(): int
    {
        return collect($this->quantities())->flatten()->sum();
    }

    public function total(): float
    {
        return round($this->lines()->sum('line_total'), 2);
    }

    /**
     * How many of this packaging the shelf can still supply, after the
     * basket's other packaging of the same medicine.
     */
    private function onShelfFor(Medicine $medicine, Packaging $packaging): int
    {
        $stripsPer = $medicine->stripsPer($packaging);
        $heldByOtherPackaging = $this->stripsOf($medicine) - $this->quantityOf($medicine, $packaging) * $stripsPer;

        return max(0, intdiv($medicine->stock - $heldByOtherPackaging, $stripsPer));
    }
}
