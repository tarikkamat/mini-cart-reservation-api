<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

use RuntimeException;

final class IdempotencyConflictException extends RuntimeException
{
    public function __construct(string $message = 'Idempotency-Key was reused with a different request body')
    {
        parent::__construct($message);
    }
}
