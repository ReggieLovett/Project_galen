<?php

namespace App\Support;

use Illuminate\Support\Number;
use NumberFormatter;

class Money
{
    /**
     * An amount in the kiosk's currency, e.g. "₱1,250.00". Uses the intl
     * extension when PHP has it, and a small symbol table when it doesn't.
     */
    public static function format(float|int|string $amount): string
    {
        $amount = (float) $amount;
        $currency = config('kiosk.currency');

        if (class_exists(NumberFormatter::class)) {
            return Number::currency($amount, in: $currency);
        }

        return match ($currency) {
            'PHP' => '₱'.number_format($amount, 2),
            'EUR' => '€'.number_format($amount, 2),
            'GBP' => '£'.number_format($amount, 2),
            'JPY' => '¥'.number_format($amount, 0),
            'USD' => '$'.number_format($amount, 2),
            default => $currency.' '.number_format($amount, 2),
        };
    }
}
