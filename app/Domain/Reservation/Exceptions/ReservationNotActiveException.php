<?php

declare(strict_types=1);

namespace App\Domain\Reservation\Exceptions;

use App\Domain\Reservation\Enums\ReservationStatus;
use RuntimeException;

final class ReservationNotActiveException extends RuntimeException
{
    public function __construct(
        public readonly string $reservationId,
        public readonly ReservationStatus $currentStatus,
    ) {
        parent::__construct(sprintf(
            'Reservation %s is not active (current status: %s)',
            $reservationId,
            $currentStatus->value,
        ));
    }
}
