<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kiosk identity
    |--------------------------------------------------------------------------
    */

    'name' => env('KIOSK_NAME', 'Riverside Pharmacy'),

    // ISO 4217 code, e.g. USD or PHP. Prices are formatted with Number::currency().
    'currency' => env('KIOSK_CURRENCY', 'USD'),

    /*
    |--------------------------------------------------------------------------
    | Session timing (seconds)
    |--------------------------------------------------------------------------
    |
    | After `idle_timeout` seconds without a touch the kiosk asks "Still
    | there?" and counts down `idle_countdown` seconds before wiping the
    | basket. The receipt screen returns to "Touch to begin" on its own.
    |
    */

    'idle_timeout' => (int) env('KIOSK_IDLE_TIMEOUT', 90),

    'idle_countdown' => (int) env('KIOSK_IDLE_COUNTDOWN', 15),

    'receipt_timeout' => (int) env('KIOSK_RECEIPT_TIMEOUT', 30),

    'max_quantity_per_item' => 10,

    /*
    |--------------------------------------------------------------------------
    | Staff area
    |--------------------------------------------------------------------------
    |
    | Nothing on the kiosk links here. Staff open it by URL, or by tapping
    | the logo on the "Touch to begin" screen five times in a row. The
    | seeded admin uses the email and password below.
    |
    */

    'admin' => [
        'path' => env('KIOSK_ADMIN_PATH', 'staff-portal'),
        'email' => env('KIOSK_ADMIN_EMAIL', 'admin@riverside.test'),
        'password' => env('KIOSK_ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Prescription matching
    |--------------------------------------------------------------------------
    |
    | Decides what counts as "bought this prescribed drug before":
    |
    |  "ingredient" - any completed purchase of the same active ingredient at
    |                 the same strength, whatever the brand. A Lipitor 20 mg
    |                 customer may refill with generic atorvastatin 20 mg.
    |  "product"    - only a completed purchase of this exact product.
    |
    */

    'prescription_match' => env('KIOSK_PRESCRIPTION_MATCH', 'ingredient'),

    /*
    |--------------------------------------------------------------------------
    | Stock assistant (AI)
    |--------------------------------------------------------------------------
    |
    | With ANTHROPIC_API_KEY set, Claude writes the suggestion and ranks the
    | candidates. Without it, the same database lookup runs and a template
    | writes the message, so the demo works offline.
    |
    */

    'assistant' => [
        'model' => env('KIOSK_ASSISTANT_MODEL', 'claude-opus-5-5'),
        'max_alternatives' => 3,
        'timeout' => 20,
    ],

];
