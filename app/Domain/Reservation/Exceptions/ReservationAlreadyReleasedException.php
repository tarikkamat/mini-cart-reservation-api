<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

use RuntimeException;

final class ReservationAlreadyReleasedException extends RuntimeException
{
    public function __construct(public readonly string $reservationId)
    {
        parent::__construct("Reservation {$reservationId} is already released");
    }
}
