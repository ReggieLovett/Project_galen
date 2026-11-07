<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * How the customer takes a medicine home. Shelf stock is counted in strips,
 * so a box takes the medicine's `strips_per_box` strips off the shelf.
 */
enum Packaging: string
{
    /** A single blister strip ("banig"). */
    case Strip = 'strip';

    /** A sealed box of several strips. */
    case Box = 'box';

    public function label(): string
    {
        return match ($this) {
            self::Strip => 'Strip',
            self::Box => 'Box',
        };
    }

    /**
     * "1 strip", "3 boxes".
     */
    public function count(int $quantity): string
    {
        return $quantity.' '.Str::plural(Str::lower($this->label()), $quantity);
    }
}
