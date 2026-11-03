<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'category_id', 'name', 'brand', 'active_ingredient', 'strength',
    'dosage_form', 'pack_size', 'therapeutic_class', 'price', 'stock',
])]
class Medicine extends Model
{
    public const LOW_STOCK = 5;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'stock' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function requiresPrescription(): bool
    {
        return $this->category->requires_prescription;
    }

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    public function isLowStock(): bool
    {
        return $this->isInStock() && $this->stock <= self::LOW_STOCK;
    }

    /**
     * "Motrin IB", "Sandoz Atorvastatin Calcium": the name with its brand.
     */
    public function fullName(): string
    {
        return str($this->name)->startsWith($this->brand) ? $this->name : "{$this->brand} {$this->name}";
    }

    /**
     * "Ibuprofen 200 mg" - how the drug itself is named, independent of brand.
     */
    public function drugLabel(): string
    {
        return "{$this->active_ingredient} {$this->strength}";
    }

    #[Scope]
    protected function inStock(Builder $query): void
    {
        $query->where('stock', '>', 0);
    }

    #[Scope]
    protected function outOfStock(Builder $query): void
    {
        $query->where('stock', '<=', 0);
    }

    #[Scope]
    protected function lowStock(Builder $query): void
    {
        $query->inStock()->where('stock', '<=', self::LOW_STOCK);
    }

    #[Scope]
    protected function ofType(Builder $query, ?string $slug): void
    {
        if (in_array($slug, [Category::OTC, Category::PRESCRIPTION], true)) {
            $query->whereRelation('category', 'slug', $slug);
        }
    }

    #[Scope]
    protected function inClass(Builder $query, ?string $class): void
    {
        if (filled($class)) {
            $query->where('therapeutic_class', $class);
        }
    }

    #[Scope]
    protected function search(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(fn (Builder $query) => $query
            ->whereLike('name', "%{$term}%")
            ->orWhereLike('brand', "%{$term}%")
            ->orWhereLike('active_ingredient', "%{$term}%")
            ->orWhereLike('therapeutic_class', "%{$term}%"));
    }

    /**
     * Medicines with exactly the same active ingredient, in any brand.
     */
    #[Scope]
    protected function sameIngredientAs(Builder $query, Medicine $medicine): void
    {
        $query->whereRaw('LOWER(active_ingredient) = ?', [mb_strtolower(trim($medicine->active_ingredient))]);
    }

    /**
     * In-stock substitutes for an unavailable medicine: same active
     * ingredient, different brand. Closest matches (same strength, then same
     * dosage form) come first, then the cheapest.
     */
    #[Scope]
    protected function alternativesTo(Builder $query, Medicine $medicine): void
    {
        $query->sameIngredientAs($medicine)
            ->whereKeyNot($medicine->getKey())
            ->where('brand', '!=', $medicine->brand)
            ->inStock()
            ->orderByRaw('CASE WHEN strength = ? THEN 0 ELSE 1 END', [$medicine->strength])
            ->orderByRaw('CASE WHEN dosage_form = ? THEN 0 ELSE 1 END', [$medicine->dosage_form])
            ->orderBy('price');
    }
}
