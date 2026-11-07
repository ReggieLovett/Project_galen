<?php

namespace App\Models;

use App\Enums\Packaging;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'category_id', 'name', 'brand', 'active_ingredient', 'strength', 'dosage_form',
    'pieces_per_strip', 'strips_per_box', 'therapeutic_class', 'description', 'piece_price',
    'box_price', 'image_path', 'stock',
])]
class Medicine extends Model
{
    /**
     * Pieces on the shelf at or below which staff see a "running low" alert.
     * Stock is counted in single pieces (tablets, capsules).
     */
    public const LOW_STOCK = 50;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'piece_price' => 'decimal:2',
            'box_price' => 'decimal:2',
            'pieces_per_strip' => 'integer',
            'strips_per_box' => 'integer',
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
     * How many pieces one box holds, e.g. 100 tablets.
     */
    public function piecesPerBox(): int
    {
        return $this->pieces_per_strip * $this->strips_per_box;
    }

    /**
     * How many pieces one unit of this packaging takes off the shelf.
     */
    public function piecesPer(Packaging $packaging): int
    {
        return $packaging === Packaging::Box ? $this->piecesPerBox() : 1;
    }

    /**
     * Price of one piece or one box, e.g. "5.00" or "480.00".
     */
    public function priceOf(Packaging $packaging): string
    {
        return $packaging === Packaging::Box ? $this->box_price : $this->piece_price;
    }

    /**
     * Is there enough on the shelf for at least one of this packaging?
     */
    public function canSell(Packaging $packaging): bool
    {
        return $this->stock >= $this->piecesPer($packaging);
    }

    /**
     * What a box saves over buying its pieces one by one.
     */
    public function boxSavings(): float
    {
        return max(0, round($this->piece_price * $this->piecesPerBox() - $this->box_price, 2));
    }

    /**
     * "tablets", "capsule", "caplets": what one piece of this medicine is.
     */
    public function pieceName(int $count = 2): string
    {
        return Str::plural(Str::lower(Str::afterLast($this->dosage_form, ' ')), $count);
    }

    /**
     * "11 tablets", "1 capsule", "3 boxes".
     */
    public function countOf(Packaging $packaging, int $quantity): string
    {
        return $packaging === Packaging::Box
            ? $packaging->count($quantity)
            : "{$quantity} {$this->pieceName($quantity)}";
    }

    /**
     * "Tablet" or "Box": the packaging, named for this medicine.
     */
    public function packagingName(Packaging $packaging): string
    {
        return $packaging === Packaging::Box ? 'Box' : Str::ucfirst($this->pieceName(1));
    }

    /**
     * "Single tablet", "Box of 100 tablets".
     */
    public function packDescription(Packaging $packaging): string
    {
        return $packaging === Packaging::Box
            ? "Box of {$this->piecesPerBox()} {$this->pieceName()}"
            : 'Single '.$this->pieceName(1);
    }

    /**
     * The product photo, when staff have added one under public/.
     */
    public function imageUrl(): ?string
    {
        return $this->image_path ? asset($this->image_path) : null;
    }

    /**
     * "Biogesic", "RiteMed Paracetamol": the name with its brand.
     */
    public function fullName(): string
    {
        return str($this->name)->startsWith($this->brand) ? $this->name : "{$this->brand} {$this->name}";
    }

    /**
     * The separate ingredients of a combination product:
     * "Phenylephrine + Chlorphenamine + Paracetamol" → three names.
     *
     * @return list<string>
     */
    public function ingredients(): array
    {
        return array_map(trim(...), explode('+', $this->active_ingredient));
    }

    /**
     * "Paracetamol 500 mg" - how the drug itself is named, independent of brand.
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
            ->orderBy('piece_price');
    }
}
