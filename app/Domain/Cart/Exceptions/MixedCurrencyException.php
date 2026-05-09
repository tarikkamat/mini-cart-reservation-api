<?php

declare(strict_types=1);

namespace App\Domain\Cart\Exceptions;

use RuntimeException;

final class MixedCurrencyException extends RuntimeException
{
    public function __construct(string $message = 'Cart items must share a single currency')
    {
        parent::__construct($message);
    }
}
