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

final class ExpireReservationAction
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly InventoryGuard $guard,
    ) {}

    /**
     * Idempotently transition an active reservation to the expired state and
     * release its reserved stock. Returns null when the reservation has been
     * deleted between the scheduler scan and this call.
     */
    public function execute(string $reservationId): ?Reservation
    {
        return $this->db->transaction(function () use ($reservationId): ?Reservation {
            /** @var Reservation|null $reservation */
            $reservation = Reservation::with('items')
                ->lockForUpdate()
                ->find($reservationId);

            if ($reservation === null) {
                return null;
            }

            if ($reservation->status !== ReservationStatus::Active) {
                return $reservation;
            }

            $itemQuantities = $reservation->items
                ->mapWithKeys(fn (ReservationItem $i): array => [$i->product_id => $i->quantity])
                ->all();

            $this->guard->releaseMany($itemQuantities);

            $reservation->update(['status' => ReservationStatus::Expired]);

            $this->logAudit($reservation);

            return $reservation->fresh('items');
        });
    }

    private function logAudit(Reservation $reservation): void
    {
        $productIds = $reservation->items->pluck('product_id')->all();
        $inventories = Inventory::query()->whereIn('product_id', $productIds)->get()->keyBy('product_id');

        $now = now();
        $audit = $reservation->items->map(fn (ReservationItem $item): array => [
            'product_id' => $item->product_id,
            'reservation_id' => $reservation->id,
            'type' => 'expired',
            'quantity_delta' => $item->quantity,
            'quantity_after' => $inventories[$item->product_id]->quantity,
            'reserved_after' => $inventories[$item->product_id]->reserved_quantity,
            'reason' => null,
            'created_at' => $now,
        ])->all();

        StockMovement::insert($audit);
    }
}
