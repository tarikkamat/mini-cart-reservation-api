<?php

declare(strict_types=1);

namespace App\Domain\Cart\Exceptions;

use RuntimeException;

final class ProductInactiveException extends RuntimeException
{
    public function __construct(public readonly int $productId)
    {
        parent::__construct("Product #{$productId} is not active");
    }
}
