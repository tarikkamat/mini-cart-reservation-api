<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Services;

use App\Domain\Reservation\Exceptions\InsufficientStockException;
use Illuminate\Database\DatabaseManager;

final class InventoryGuard
{
    public function __construct(private readonly DatabaseManager $db) {}

    /**
     * Atomically increment reserved_quantity for many products.
     *
     * Uses a conditional UPDATE — `affected = 0` means the available stock
     * was insufficient at the moment the row was touched. This is correct
     * under concurrency without application-level locking.
     *
     * Items are sorted by product_id ascending to prevent cross-row deadlocks
     * between concurrent multi-product reservations.
     *
     * @param  array<int, int>  $itemQuantities  productId => quantity
     */
    public function reserveMany(array $itemQuantities): void
    {
        ksort($itemQuantities);

        foreach ($itemQuantities as $productId => $qty) {
            $affected = $this->db->connection()->update(
                'UPDATE inventories
                 SET reserved_quantity = reserved_quantity + ?, updated_at = NOW()
                 WHERE product_id = ?
                   AND (quantity - reserved_quantity) >= ?',
                [$qty, $productId, $qty],
            );

            if ($affected === 0) {
                throw new InsufficientStockException($productId, $qty);
            }
        }
    }

    /**
     * Atomically decrement reserved_quantity for many products.
     *
     * The WHERE guard `reserved_quantity >= ?` makes a double-release a no-op
     * rather than an error, supporting idempotent release semantics.
     *
     * @param  array<int, int>  $itemQuantities  productId => quantity
     */
    public function releaseMany(array $itemQuantities): void
    {
        ksort($itemQuantities);

        foreach ($itemQuantities as $productId => $qty) {
            $this->db->connection()->update(
                'UPDATE inventories
                 SET reserved_quantity = reserved_quantity - ?, updated_at = NOW()
                 WHERE product_id = ? AND reserved_quantity >= ?',
                [$qty, $productId, $qty],
            );
        }
    }
}
