<?php

declare(strict_types=1);

namespace App\Domain\Cart\Data;

use Illuminate\Support\Collection;

final readonly class CartQuoteData
{
    /**
     * @param  Collection<int, CartItemData>  $items
     */
    public function __construct(
        public string $customerEmail,
        public Collection $items,
    ) {}
}
