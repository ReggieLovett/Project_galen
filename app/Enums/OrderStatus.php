<?php

namespace App\Enums;

enum OrderStatus: string
{
    /** Placed at the kiosk; the customer still has to pay at the counter. */
    case AwaitingPayment = 'awaiting_payment';

    /** Paid and handed over. Only completed orders count as purchase history. */
    case Completed = 'completed';

    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Pay at counter',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }
}
