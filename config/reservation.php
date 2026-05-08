<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Reservation TTL (minutes)
    |--------------------------------------------------------------------------
    |
    | How long a newly created reservation stays in the "active" state before
    | the scheduled expiration job releases its stock back to the inventory.
    |
    */

    'ttl_minutes' => (int) env('RESERVATION_TTL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Idempotency cache TTL (hours)
    |--------------------------------------------------------------------------
    |
    | How long the Redis-backed idempotency store keeps the response payload
    | for a given Idempotency-Key. After this window expires, replays are
    | treated as fresh requests.
    |
    */

    'idempotency_ttl_hours' => (int) env('IDEMPOTENCY_TTL_HOURS', 24),

];
