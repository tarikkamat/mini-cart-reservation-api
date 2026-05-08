<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Data;

use App\Domain\Cart\Data\CartItemData;
use Illuminate\Support\Collection;

final readonly class CreateReservationData
{
    /**
     * @param  Collection<int, CartItemData>  $items
     */
    public function __construct(
        public string $customerEmail,
        public Collection $items,
        public ?string $idempotencyKey = null,
    ) {}
}
