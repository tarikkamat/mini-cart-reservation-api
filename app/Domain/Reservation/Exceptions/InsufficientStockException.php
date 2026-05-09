<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

use RuntimeException;

final class InsufficientStockException extends RuntimeException
{
    public function __construct(
        public readonly int $productId,
        public readonly int $requestedQuantity,
        public readonly ?int $availableQuantity = null,
    ) {
        parent::__construct(sprintf(
            'Insufficient stock for product #%d: requested %d, available %s',
            $productId,
            $requestedQuantity,
            $availableQuantity ?? 'unknown',
        ));
    }
}
