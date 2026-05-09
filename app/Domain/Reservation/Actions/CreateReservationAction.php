<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions;

use App\Domain\Cart\Data\CartItemData;
use App\Domain\Cart\Services\CartCalculator;
use App\Domain\Reservation\Data\CreateReservationData;
use App\Domain\Reservation\Enums\ReservationStatus;
use App\Domain\Reservation\Services\InventoryGuard;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\StockMovement;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Str;

final class CreateReservationAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly CartCalculator $calculator,
        private readonly InventoryGuard $guard,
    ) {}

    public function execute(CreateReservationData $data): Reservation
    {
        return $this->db->transaction(function () use ($data): Reservation {
            $products = Product::query()
                ->with(['activePrice', 'inventory'])
                ->whereIn('id', $data->items->pluck('productId')->all())
                ->get()
                ->keyBy('id');

            $calculated = $this->calculator->calculate($data->items, $products);

            $itemQuantities = $data->items
                ->mapWithKeys(fn (CartItemData $i): array => [$i->productId => $i->quantity])
                ->all();

            $this->guard->reserveMany($itemQuantities);

            $reservation = Reservation::create([
                'id' => (string) Str::uuid(),
                'customer_email' => $data->customerEmail,
                'status' => ReservationStatus::Active,
                'subtotal' => $calculated->subtotal,
                'currency' => $calculated->currency,
                'expires_at' => now()->addMinutes((int) config('reservation.ttl_minutes')),
                'idempotency_key' => $data->idempotencyKey,
            ]);

            $reservation->items()->createMany(
                $calculated->lines->map(fn ($line): array => [
                    'product_id' => $line->productId,
                    'quantity' => $line->quantity,
                    'unit_price' => $line->unitPrice,
                    'line_total' => $line->lineTotal,
                ])->all(),
            );

            $now = now();
            $audit = $data->items->map(function (CartItemData $item) use ($products, $reservation, $now): array {
                $inventory = $products[$item->productId]->inventory;

                return [
                    'product_id' => $item->productId,
                    'reservation_id' => $reservation->id,
                    'type' => 'reserved',
                    'quantity_delta' => -$item->quantity,
                    'quantity_after' => $inventory->quantity,
                    'reserved_after' => $inventory->reserved_quantity + $item->quantity,
                    'reason' => null,
                    'created_at' => $now,
                ];
            })->all();

            StockMovement::insert($audit);

            return $reservation->load('items');
        });
    }
}
