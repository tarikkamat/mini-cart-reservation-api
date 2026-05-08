<?php

declare(strict_types=1);

namespace App\Domain\Cart\Data;

final readonly class CalculatedLine
{
    public function __construct(
        public int $productId,
        public string $name,
        public string $sku,
        public int $quantity,
        public string $unitPrice,
        public string $lineTotal,
    ) {}
}
