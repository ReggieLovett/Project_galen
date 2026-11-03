<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'requires_prescription'])]
class Category extends Model
{
    public const OTC = 'otc';

    public const PRESCRIPTION = 'prescription';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requires_prescription' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Medicine, $this>
     */
    public function medicines(): HasMany
    {
        return $this->hasMany(Medicine::class);
    }
}
