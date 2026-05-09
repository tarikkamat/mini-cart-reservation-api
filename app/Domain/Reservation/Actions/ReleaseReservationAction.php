<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Actions;

use App\Domain\Reservation\Enums\ReservationStatus;
use App\Domain\Reservation\Services\InventoryGuard;
use App\Models\Inventory;
use App\Models\Reservation;
use App\Models\ReservationItem;
use App\Models\StockMovement;
use Illuminate\Database\DatabaseManager;

final class ReleaseReservationAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly InventoryGuard $guard,
    ) {}

    public function execute(string $reservationId): Reservation
    {
        return $this->db->transaction(function () use ($reservationId): Reservation {
            /** @var Reservation $reservation */
            $reservation = Reservation::with('items')
                ->lockForUpdate()
                ->findOrFail($reservationId);

            // Idempotent: any non-active state is treated as a no-op.
            if ($reservation->status !== ReservationStatus::Active) {
                return $reservation;
            }

            $itemQuantities = $reservation->items
                ->mapWithKeys(fn (ReservationItem $i): array => [$i->product_id => $i->quantity])
                ->all();

            $this->guard->releaseMany($itemQuantities);

            $reservation->update([
                'status' => ReservationStatus::Released,
                'released_at' => now(),
            ]);

            $this->logAudit($reservation, type: 'released');

            return $reservation->fresh('items');
        });
    }

    private function logAudit(Reservation $reservation, string $type): void
    {
        $productIds = $reservation->items->pluck('product_id')->all();
        $inventories = Inventory::query()->whereIn('product_id', $productIds)->get()->keyBy('product_id');

        $now = now();
        $audit = $reservation->items->map(fn (ReservationItem $item): array => [
            'product_id' => $item->product_id,
            'reservation_id' => $reservation->id,
            'type' => $type,
            'quantity_delta' => $item->quantity,
            'quantity_after' => $inventories[$item->product_id]->quantity,
            'reserved_after' => $inventories[$item->product_id]->reserved_quantity,
            'reason' => null,
            'created_at' => $now,
        ])->all();

        StockMovement::insert($audit);
    }
}
