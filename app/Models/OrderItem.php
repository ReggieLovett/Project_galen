<?php

namespace App\Models;

use App\Enums\Packaging;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['medicine_id', 'name', 'brand', 'strength', 'packaging', 'unit_price', 'quantity', 'piece_count', 'line_total'])]
class OrderItem extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'packaging' => Packaging::class,
            'unit_price' => 'decimal:2',
            'line_total' => 'decimal:2',
            'quantity' => 'integer',
            'piece_count' => 'integer',
        ];
    }

    /**
     * "11 tablets of Biogesic", "1 box of Amoxil": what the counter fetches.
     * Load the medicine first for "tablets"; without it, "11 pieces".
     */
    public function summary(): string
    {
        $count = $this->relationLoaded('medicine') && $this->medicine
            ? $this->medicine->countOf($this->packaging, $this->quantity)
            : $this->packaging->count($this->quantity);

        return "{$count} of {$this->name}";
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Medicine, $this>
     */
    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
