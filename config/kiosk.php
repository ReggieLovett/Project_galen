<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Kiosk identity
    |--------------------------------------------------------------------------
    */

    'name' => env('KIOSK_NAME', 'Galen Pharmacy'),

    // ISO 4217 code. Philippine pesos (₱) by default; prices are formatted by <x-money>.
    'currency' => env('KIOSK_CURRENCY', 'PHP'),

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

    // The receipt screen "prints" the slip on its own after this many seconds,
    // or straight away when the customer taps Print. A demo: nothing is sent
    // to a real printer, the slip feeds out on screen.
    'receipt_print_delay' => (int) env('KIOSK_RECEIPT_PRINT_DELAY', 10),

    // Most pieces (or boxes) of one medicine a customer may take per visit.
    // Empty: no limit, only shelf stock, so one customer can buy 11 tablets
    // and the next 3. Set KIOSK_MAX_PER_ITEM to a number to cap it.
    'max_quantity_per_item' => is_numeric(env('KIOSK_MAX_PER_ITEM')) ? (int) env('KIOSK_MAX_PER_ITEM') : null,

    /*
    |--------------------------------------------------------------------------
    | Staff area
    |--------------------------------------------------------------------------
    |
    | Nothing on the kiosk links here. On the kiosk itself, staff double-tap
    | the logo on the "Touch to begin" screen (a kiosk browser has no
    | address bar). Other devices open the URL. The seeded
    | admin uses the email and password below.
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
    | With ANTHROPIC_API_KEY or GROQ_API_KEY set, the AI writes the suggestion
    | and ranks the candidates. Without either, the same database lookup runs
    | and a template writes the message, so the demo works offline.
    |
    | provider: "anthropic" or "groq". Left empty, whichever key is set is
    | used (Anthropic first if both are). The Groq model is GROQ_MODEL in
    | config/services.php; `model` below is the Claude model.
    |
    */

    'assistant' => [
        'provider' => env('KIOSK_ASSISTANT_PROVIDER'),
        'model' => env('KIOSK_ASSISTANT_MODEL', 'claude-opus-5-5'),
        'max_alternatives' => 3,
        'timeout' => 20,
    ],

];
