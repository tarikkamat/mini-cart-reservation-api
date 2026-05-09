<?php

declare(strict_types=1);

use App\Domain\Reservation\Enums\ReservationStatus;
use App\Models\Reservation;
use App\Models\StockMovement;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('creates an active reservation, persists items, and increments reserved stock', function (): void {
    $product = makeProductWithStock(stock: 10, price: '100.00');

    $response = $this->postJson('/api/reservations', [
        'customer_email' => 'u@x.com',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ])->assertCreated();

    $response->assertJsonPath('data.status', ReservationStatus::Active->value)
        ->assertJsonPath('data.subtotal', '200.00')
        ->assertJsonPath('data.currency', 'TRY')
        ->assertJsonPath('data.items.0.quantity', 2)
        ->assertJsonPath('data.items.0.unit_price', '100.00')
        ->assertJsonPath('data.items.0.line_total', '200.00');

    expect(Reservation::count())->toBe(1);
    expect($product->inventory->refresh()->reserved_quantity)->toBe(2);

    $reservation = Reservation::firstOrFail();
    expect($reservation->expires_at->isAfter(now()->addMinutes(14)))->toBeTrue();
    expect($reservation->expires_at->isBefore(now()->addMinutes(16)))->toBeTrue();
});

it('persists a multi-item reservation with correct subtotal and audit log', function (): void {
    $a = makeProductWithStock(stock: 10, price: '50.00');
    $b = makeProductWithStock(stock: 10, price: '25.00');

    $response = $this->postJson('/api/reservations', [
        'customer_email' => 'u@x.com',
        'items' => [
            ['product_id' => $a->id, 'quantity' => 2],
            ['product_id' => $b->id, 'quantity' => 4],
        ],
    ])->assertCreated();

    expect($response->json('data.subtotal'))->toBe('200.00');

    $reservationId = $response->json('data.id');
    $audits = StockMovement::where('reservation_id', $reservationId)->orderBy('product_id')->get();
    expect($audits)->toHaveCount(2);
    expect($audits[0]->type)->toBe('reserved');
    expect($audits[0]->quantity_delta)->toBe(-2);
    expect($audits[0]->reserved_after)->toBe(2);
    expect($audits[1]->quantity_delta)->toBe(-4);
    expect($audits[1]->reserved_after)->toBe(4);
});

it('rejects reservation when any item exceeds available stock with INSUFFICIENT_STOCK 409', function (): void {
    $product = makeProductWithStock(stock: 3);

    $response = $this->postJson('/api/reservations', [
        'customer_email' => 'u@x.com',
        'items' => [['product_id' => $product->id, 'quantity' => 10]],
    ])->assertStatus(409);

    $response->assertJsonPath('error.code', 'INSUFFICIENT_STOCK')
        ->assertJsonPath('error.details.0.product_id', $product->id)
        ->assertJsonPath('error.details.0.requested', 10);

    expect(Reservation::count())->toBe(0);
    expect($product->inventory->refresh()->reserved_quantity)->toBe(0);
});

it('replays the same response and persists exactly one reservation when an Idempotency-Key is reused', function (): void {
    $product = makeProductWithStock(stock: 50);
    $key = (string) Str::uuid();

    $first = $this->postJson('/api/reservations', [
        'customer_email' => 'u@x.com',
        'items' => [['product_id' => $product->id, 'quantity' => 2]],
    ], ['Idempotency-Key' => $key])->assertCreated();

    $reservationId = $first->json('data.id');
    $bodies = [$first->getContent()];

    for ($i = 0; $i < 4; $i++) {
        $replay = $this->postJson('/api/reservations', [
            'customer_email' => 'u@x.com',
            'items' => [['product_id' => $product->id, 'quantity' => 2]],
        ], ['Idempotency-Key' => $key])->assertCreated();

        expect($replay->headers->get('Idempotent-Replay'))->toBe('true');
        $bodies[] = $replay->getContent();
    }

    expect(array_unique($bodies))->toHaveCount(1, 'all replays must return identical bodies');
    expect(Reservation::count())->toBe(1);
    expect(Reservation::firstOrFail()->id)->toBe($reservationId);
    expect($product->inventory->refresh()->reserved_quantity)->toBe(2);
});

it('rejects an Idempotency-Key reuse with a different body as IDEMPOTENCY_CONFLICT 422', function (): void {
    $product = makeProductWithStock(stock: 50);
    $key = (string) Str::uuid();

    $this->postJson('/api/reservations', [
        'customer_email' => 'a@x.com',
        'items' => [['product_id' => $product->id, 'quantity' => 1]],
    ], ['Idempotency-Key' => $key])->assertCreated();

    $this->postJson('/api/reservations', [
        'customer_email' => 'b@x.com',
        'items' => [['product_id' => $product->id, 'quantity' => 99]],
    ], ['Idempotency-Key' => $key])
        ->assertStatus(422)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_CONFLICT');

    expect(Reservation::count())->toBe(1, 'second call must not create a second reservation');
});

it('enforces the reserved_lte_quantity CHECK constraint at the PostgreSQL level (defense-in-depth)', function (): void {
    $product = makeProductWithStock(stock: 5);

    expect(fn () => DB::statement(
        'UPDATE inventories SET reserved_quantity = quantity + 1 WHERE product_id = ?',
        [$product->id],
    ))->toThrow(QueryException::class, 'reserved_lte_quantity');
});
