<?php

namespace App\Services;

use App\Models\Medicine;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * The kiosk basket, kept in the session as [medicine id => quantity].
 */
class Cart
{
    private const KEY = 'kiosk.cart';

    public function __construct(private Session $session) {}

    /**
     * @return array<int, int>
     */
    public function quantities(): array
    {
        return $this->session->get(self::KEY, []);
    }

    public function isEmpty(): bool
    {
        return $this->quantities() === [];
    }

    public function has(Medicine $medicine): bool
    {
        return $this->quantityOf($medicine) > 0;
    }

    public function quantityOf(Medicine $medicine): int
    {
        return $this->quantities()[$medicine->id] ?? 0;
    }

    public function add(Medicine $medicine, int $quantity = 1): void
    {
        $this->put($medicine, $this->quantityOf($medicine) + $quantity);
    }

    /**
     * Set an item's quantity, capped by shelf stock and the per-item limit.
     *
     * @throws ValidationException
     */
    public function put(Medicine $medicine, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->remove($medicine);

            return;
        }

        if (! $medicine->isInStock()) {
            throw ValidationException::withMessages([
                'medicine_id' => "{$medicine->name} is out of stock. Ask the assistant for an alternative brand.",
            ]);
        }

        $limit = min($medicine->stock, config('kiosk.max_quantity_per_item'));

        if ($quantity > $limit) {
            throw ValidationException::withMessages([
                'quantity' => $limit === $medicine->stock
                    ? "Only {$limit} of {$medicine->name} left in stock."
                    : "You can buy up to {$limit} of {$medicine->name} per visit.",
            ]);
        }

        $this->session->put(self::KEY.'.'.$medicine->id, $quantity);
    }

    public function remove(Medicine $medicine): void
    {
        $this->session->forget(self::KEY.'.'.$medicine->id);
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
     * The basket's lines, sorted by name, with what each one costs.
     *
     * @return Collection<int, array{medicine: Medicine, quantity: int, line_total: float, max_quantity: int}>
     */
    public function lines(): Collection
    {
        $quantities = $this->quantities();

        return $this->medicines()
            ->sortBy('name')
            ->map(fn (Medicine $medicine) => [
                'medicine' => $medicine,
                'quantity' => $quantities[$medicine->id],
                'line_total' => round($medicine->price * $quantities[$medicine->id], 2),
                'max_quantity' => min($medicine->stock, config('kiosk.max_quantity_per_item')),
            ])
            ->values()
            ->toBase();
    }

    public function count(): int
    {
        return array_sum($this->quantities());
    }

    public function total(): float
    {
        return round($this->lines()->sum('line_total'), 2);
    }
}
