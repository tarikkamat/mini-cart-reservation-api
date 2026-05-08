<?php

declare(strict_types=1);

namespace App\Domain\Cart\Data;

use Illuminate\Support\Collection;

final readonly class CartQuoteResult
{
    /**
     * @param  Collection<int, CalculatedLine>  $lines
     */
    public function __construct(
        public Collection $lines,
        public string $subtotal,
        public string $currency,
    ) {}
}
