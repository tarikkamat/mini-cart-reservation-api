<?php

declare(strict_types=1);

use App\Domain\Reservation\Enums\ReservationStatus;
use App\Models\Reservation;

function createReservationViaApi(array $items, string $email = 'u@x.com'): array
{
    return test()->postJson('/api/reservations', [
        'customer_email' => $email,
        'items' => $items,
    ])->assertCreated()->json('data');
}

it('releases an active reservation and restores stock', function (): void {
    $product = makeProductWithStock(stock: 10);
    $created = createReservationViaApi([['product_id' => $product->id, 'quantity' => 3]]);

    expect($product->inventory->refresh()->reserved_quantity)->toBe(3);

    $response = test()->postJson("/api/reservations/{$created['id']}/release")->assertOk();

    expect($response->json('data.status'))->toBe(ReservationStatus::Released->value);
    expect($response->json('data.released_at'))->not->toBeNull();
    expect($product->inventory->refresh()->reserved_quantity)->toBe(0);
});

it('treats a double release idempotently (stock restored only once)', function (): void {
    $product = makeProductWithStock(stock: 10);
    $created = createReservationViaApi([['product_id' => $product->id, 'quantity' => 4]]);

    test()->postJson("/api/reservations/{$created['id']}/release")->assertOk();
    expect($product->inventory->refresh()->reserved_quantity)->toBe(0);

    $secondResponse = test()->postJson("/api/reservations/{$created['id']}/release")->assertOk();

    expect($secondResponse->json('data.status'))->toBe(ReservationStatus::Released->value);
    expect($product->inventory->refresh()->reserved_quantity)
        ->toBe(0, 'second release must not decrement the inventory again');
});

it('returns the current state for an already-expired reservation without changing stock', function (): void {
    $product = makeProductWithStock(stock: 10, reserved: 2);
    /** @var Reservation $reservation */
    $reservation = Reservation::factory()
        ->expired()
        ->create([
            'customer_email' => 'u@x.com',
            'subtotal' => '100.00',
            'currency' => 'TRY',
        ]);
    $reservation->items()->create([
        'product_id' => $product->id,
        'quantity' => 2,
        'unit_price' => '50.00',
        'line_total' => '100.00',
    ]);

    $response = test()->postJson("/api/reservations/{$reservation->id}/release")->assertOk();

    expect($response->json('data.status'))->toBe(ReservationStatus::Expired->value);
    expect($product->inventory->refresh()->reserved_quantity)
        ->toBe(2, 'expired reservations should not change stock on release call');
});

it('returns 404 when the reservation does not exist', function (): void {
    test()->postJson('/api/reservations/00000000-0000-0000-0000-000000000000/release')
        ->assertNotFound()
        ->assertJsonPath('error.code', 'NOT_FOUND');
});
