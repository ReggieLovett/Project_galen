<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * How the customer takes a medicine home: any number of single pieces
 * (tablets, capsules), or whole boxes. Shelf stock is counted in pieces, so
 * a box takes Medicine::piecesPerBox() pieces off the shelf.
 */
enum Packaging: string
{
    /** One tablet, capsule or caplet, cut from the blister ("tingi"). */
    case Piece = 'piece';

    /** A sealed box. */
    case Box = 'box';

    public function label(): string
    {
        return match ($this) {
            self::Piece => 'Piece',
            self::Box => 'Box',
        };
    }

    /**
     * "1 piece", "3 boxes". Medicine::countOf() says "11 tablets" instead.
     */
    public function count(int $quantity): string
    {
        return $quantity.' '.Str::plural(Str::lower($this->label()), $quantity);
    }
}
