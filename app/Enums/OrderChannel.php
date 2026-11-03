<?php

namespace App\Enums;

enum OrderChannel: string
{
    /** Placed by the customer at the self-service kiosk. */
    case Kiosk = 'kiosk';

    /** Sold by staff at the pharmacy counter. */
    case Counter = 'counter';
}
